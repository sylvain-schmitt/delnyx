<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\TransmitCreditNoteToPAMessage;
use App\Repository\CompanySettingsRepository;
use App\Repository\CreditNoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TransmitCreditNoteToPAHandler
{
    private const SUPERPDP_BASE_URL = 'https://api.superpdp.tech';

    public function __construct(
        private CreditNoteRepository $creditNoteRepository,
        private CompanySettingsRepository $companySettingsRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(TransmitCreditNoteToPAMessage $message): void
    {
        $creditNote = $this->creditNoteRepository->find($message->getCreditNoteId());

        if (!$creditNote) {
            $this->logger->warning('TransmitCreditNoteToPAHandler: credit note not found', [
                'creditNoteId' => $message->getCreditNoteId(),
            ]);
            return;
        }

        if (!$creditNote->isB2BEInvoicing()) {
            $this->logger->info('TransmitCreditNoteToPAHandler: credit note is not B2B e-invoicing, skipped', [
                'creditNoteId' => $creditNote->getId(),
            ]);
            return;
        }

        if (in_array($creditNote->getPdpStatus(), ['PENDING', 'DEPOSITED', 'ACCEPTED'], true)) {
            $this->logger->info('TransmitCreditNoteToPAHandler: credit note already submitted, skipping', [
                'creditNoteId' => $creditNote->getId(),
                'pdpStatus'    => $creditNote->getPdpStatus(),
            ]);
            return;
        }

        $settings = null;
        if ($creditNote->getCompanyId()) {
            $settings = $this->companySettingsRepository->findByCompanyId($creditNote->getCompanyId());
        }
        if (!$settings) {
            $settings = $this->companySettingsRepository->findOneBy([]);
        }

        if (!$settings || !$settings->getPdpClientId() || !$settings->getPdpApiKey()) {
            $this->logger->error('TransmitCreditNoteToPAHandler: PDP credentials not configured', [
                'creditNoteId' => $creditNote->getId(),
            ]);
            $creditNote->setPdpStatus('ERROR');
            $creditNote->setPdpResponse('Configuration PA manquante : client_id ou client_secret non renseignés.');
            $this->entityManager->flush();
            return;
        }

        try {
            $accessToken = $this->fetchAccessToken(
                trim($settings->getPdpClientId()),
                trim($settings->getPdpApiKey())
            );
        } catch (\Exception $e) {
            $this->logger->error('TransmitCreditNoteToPAHandler: OAuth token error', [
                'creditNoteId' => $creditNote->getId(),
                'error'        => $e->getMessage(),
            ]);
            $creditNote->setPdpStatus('ERROR');
            $creditNote->setPdpResponse('Erreur OAuth : ' . $e->getMessage());
            $this->entityManager->flush();
            throw $e;
        }

        $pdpCompanyId = $settings->getPdpCompanyId();
        $pdpCompanySiren = null;
        if (!$pdpCompanyId) {
            try {
                [$pdpCompanyId, $pdpCompanySiren] = $this->fetchCompanyInfo($accessToken);
                $settings->setPdpCompanyId($pdpCompanyId);
                $this->entityManager->flush();
            } catch (\Exception $e) {
                $this->logger->error('TransmitCreditNoteToPAHandler: company ID discovery failed', [
                    'creditNoteId' => $creditNote->getId(),
                    'error'        => $e->getMessage(),
                ]);
                $creditNote->setPdpStatus('ERROR');
                $creditNote->setPdpResponse('Erreur récupération company_id Super PDP : ' . $e->getMessage());
                $this->entityManager->flush();
                return;
            }
        }

        if ($pdpCompanySiren === null) {
            try {
                [, $pdpCompanySiren] = $this->fetchCompanyInfo($accessToken);
            } catch (\Exception $e) {
                $pdpCompanySiren = $settings->getSiren();
            }
        }

        $externalId = substr($creditNote->getNumber() ?? (string) $creditNote->getId(), 0, 36);

        try {
            $ciiXml = $this->buildCiiXml($creditNote, $settings, $pdpCompanyId, $pdpCompanySiren);

            $queryParams = ['external_id' => $externalId];
            if ($settings->getPdpMode() === 'sandbox') {
                $queryParams['disable_pre_check'] = 'true';
            }
            $ch = curl_init(self::SUPERPDP_BASE_URL . '/v1.beta/invoices?' . http_build_query($queryParams));
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/xml',
                    'Accept: application/json',
                ],
                CURLOPT_POSTFIELDS => $ciiXml,
            ]);
            $responseBody = curl_exec($ch);
            $statusCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($statusCode >= 200 && $statusCode < 300) {
                $creditNote->setPdpStatus('PENDING');
                $creditNote->setPdpProvider('superpdp');
                $creditNote->setPdpTransmissionDate(new \DateTime());
                $creditNote->setPdpResponse($responseBody);

                $this->logger->info('TransmitCreditNoteToPAHandler: credit note transmitted to Super PDP', [
                    'creditNoteId' => $creditNote->getId(),
                    'number'       => $creditNote->getNumber(),
                    'statusCode'   => $statusCode,
                ]);
            } else {
                $creditNote->setPdpStatus('ERROR');
                $creditNote->setPdpResponse($responseBody);

                $this->logger->error('TransmitCreditNoteToPAHandler: Super PDP returned error', [
                    'creditNoteId' => $creditNote->getId(),
                    'statusCode'   => $statusCode,
                    'response'     => $responseBody,
                ]);
            }
        } catch (\Exception $e) {
            $creditNote->setPdpStatus('ERROR');
            $creditNote->setPdpResponse($e->getMessage());
            $this->logger->error('TransmitCreditNoteToPAHandler: exception during PA transmission', [
                'creditNoteId' => $creditNote->getId(),
                'exception'    => $e->getMessage(),
            ]);
            throw $e;
        }

        $this->entityManager->flush();
    }

    private function buildCiiXml(\App\Entity\CreditNote $creditNote, \App\Entity\CompanySettings $settings, int $pdpCompanyId, string $pdpCompanySiren): string
    {
        $invoice = $creditNote->getInvoice();
        $client  = $invoice?->getClient();

        $totalHT  = number_format((float) $creditNote->getMontantHT(), 2, '.', '');
        $totalTTC = number_format((float) $creditNote->getMontantTTC(), 2, '.', '');
        $issueDate = ($creditNote->getDateEmission() ?? new \DateTime())->format('Ymd');

        $linesXml = '';
        foreach ($creditNote->getLines() as $index => $line) {
            $lineId    = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $lineHt    = number_format((float) ($line->getTotalHt() ?? 0), 2, '.', '');
            $unitPrice = number_format((float) ($line->getUnitPrice() ?? 0), 2, '.', '');
            $qty       = number_format((float) ($line->getQuantity() ?? 1), 3, '.', '');
            $desc      = htmlspecialchars($line->getDescription() ?? '', ENT_XML1);
            $linesXml .= <<<XML

    <IncludedSupplyChainTradeLineItem xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
      <AssociatedDocumentLineDocument xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
        <LineID xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$lineId}</LineID>
      </AssociatedDocumentLineDocument>
      <SpecifiedTradeProduct xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
        <Name xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$desc}</Name>
      </SpecifiedTradeProduct>
      <SpecifiedLineTradeAgreement xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
        <NetPriceProductTradePrice xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
          <ChargeAmount xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$unitPrice}</ChargeAmount>
        </NetPriceProductTradePrice>
      </SpecifiedLineTradeAgreement>
      <SpecifiedLineTradeDelivery xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
        <BilledQuantity xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" unitCode="C62">{$qty}</BilledQuantity>
      </SpecifiedLineTradeDelivery>
      <SpecifiedLineTradeSettlement xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
        <ApplicableTradeTax xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
          <TypeCode xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">VAT</TypeCode>
          <CategoryCode xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">E</CategoryCode>
          <RateApplicablePercent xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">0</RateApplicablePercent>
        </ApplicableTradeTax>
        <SpecifiedTradeSettlementLineMonetarySummation xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
          <LineTotalAmount xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$lineHt}</LineTotalAmount>
        </SpecifiedTradeSettlementLineMonetarySummation>
      </SpecifiedLineTradeSettlement>
    </IncludedSupplyChainTradeLineItem>
XML;
        }

        $sellerName        = htmlspecialchars($settings->getRaisonSociale() ?? '', ENT_XML1);
        $sellerAddr        = htmlspecialchars($settings->getAdresse() ?? '', ENT_XML1);
        $sellerCity        = htmlspecialchars($settings->getVille() ?? '', ENT_XML1);
        $sellerPostal      = htmlspecialchars($settings->getCodePostal() ?? '', ENT_XML1);
        $sellerSiren       = htmlspecialchars($pdpCompanySiren ?: ($settings->getSiren() ?? ''), ENT_XML1);
        $sellerElecAddress = $sellerSiren . '_' . $pdpCompanyId;

        $buyerName    = htmlspecialchars($client?->getCompanyName() ?? $client?->getNomComplet() ?? '', ENT_XML1);
        $buyerAddr    = htmlspecialchars($client?->getAdresse() ?? '', ENT_XML1);
        $buyerCity    = htmlspecialchars($client?->getVille() ?? '', ENT_XML1);
        $buyerPostal  = htmlspecialchars($client?->getCodePostal() ?? '', ENT_XML1);
        $buyerSiren   = htmlspecialchars($client?->getSiren() ?? '', ENT_XML1);

        $rawPdpAddr = $client?->getPdpElectronicAddress();
        if ($rawPdpAddr && str_contains($rawPdpAddr, ':')) {
            [$buyerElecScheme, $buyerElecValue] = explode(':', $rawPdpAddr, 2);
            $buyerElecScheme = htmlspecialchars($buyerElecScheme, ENT_XML1);
            $buyerElecValue  = htmlspecialchars($buyerElecValue, ENT_XML1);
        } else {
            $buyerElecScheme = '0002';
            $buyerElecValue  = $buyerSiren;
        }

        $creditNoteNumber = htmlspecialchars($creditNote->getNumber() ?? '', ENT_XML1);

        // Référence à la facture d'origine (BT-25 / BT-26)
        $invoiceNumber = htmlspecialchars($invoice?->getNumero() ?? '', ENT_XML1);
        $invoiceDate   = ($invoice?->getDateCreation() ?? new \DateTime())->format('Ymd');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<CrossIndustryInvoice xmlns="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100">
  <ExchangedDocumentContext xmlns="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100">
    <BusinessProcessSpecifiedDocumentContextParameter xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
      <ID xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">M1</ID>
    </BusinessProcessSpecifiedDocumentContextParameter>
    <GuidelineSpecifiedDocumentContextParameter xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
      <ID xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">urn:cen.eu:en16931:2017</ID>
    </GuidelineSpecifiedDocumentContextParameter>
  </ExchangedDocumentContext>
  <ExchangedDocument xmlns="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100">
    <ID xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$creditNoteNumber}</ID>
    <TypeCode xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">381</TypeCode>
    <IssueDateTime xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
      <DateTimeString xmlns="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100" format="102">{$issueDate}</DateTimeString>
    </IssueDateTime>
    <IncludedNote xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
      <Content xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$creditNote->getReason()}</Content>
    </IncludedNote>
    <IncludedNote xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
      <Content xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">L&#39;indemnit&#233; forfaitaire l&#233;gale pour frais de recouvrement est de 40 &#8364;.</Content>
      <SubjectCode xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">PMT</SubjectCode>
    </IncludedNote>
    <IncludedNote xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
      <Content xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">En cas de retard de paiement, des p&#233;nalit&#233;s de retard au taux l&#233;gal en vigueur seront appliqu&#233;es.</Content>
      <SubjectCode xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">PMD</SubjectCode>
    </IncludedNote>
    <IncludedNote xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
      <Content xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">Aucun escompte pour paiement anticip&#233;.</Content>
      <SubjectCode xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">AAB</SubjectCode>
    </IncludedNote>
  </ExchangedDocument>
  <SupplyChainTradeTransaction xmlns="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100">{$linesXml}
    <ApplicableHeaderTradeAgreement xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
      <SellerTradeParty xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
        <Name xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$sellerName}</Name>
        <SpecifiedLegalOrganization xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
          <ID xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" schemeID="0002">{$sellerSiren}</ID>
        </SpecifiedLegalOrganization>
        <PostalTradeAddress xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
          <PostcodeCode xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$sellerPostal}</PostcodeCode>
          <LineOne xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$sellerAddr}</LineOne>
          <CityName xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$sellerCity}</CityName>
          <CountryID xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">FR</CountryID>
        </PostalTradeAddress>
        <URIUniversalCommunication xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
          <URIID xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" schemeID="0225">{$sellerElecAddress}</URIID>
        </URIUniversalCommunication>
        <SpecifiedTaxRegistration xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
          <ID xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" schemeID="FC">{$sellerSiren}</ID>
        </SpecifiedTaxRegistration>
      </SellerTradeParty>
      <BuyerTradeParty xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
        <Name xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$buyerName}</Name>
        <SpecifiedLegalOrganization xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
          <ID xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" schemeID="0002">{$buyerSiren}</ID>
        </SpecifiedLegalOrganization>
        <PostalTradeAddress xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
          <PostcodeCode xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$buyerPostal}</PostcodeCode>
          <LineOne xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$buyerAddr}</LineOne>
          <CityName xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$buyerCity}</CityName>
          <CountryID xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">FR</CountryID>
        </PostalTradeAddress>
        <URIUniversalCommunication xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
          <URIID xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" schemeID="{$buyerElecScheme}">{$buyerElecValue}</URIID>
        </URIUniversalCommunication>
      </BuyerTradeParty>
    </ApplicableHeaderTradeAgreement>
    <ApplicableHeaderTradeDelivery xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
      <ShipToTradeParty xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
        <PostalTradeAddress xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
          <CountryID xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">FR</CountryID>
        </PostalTradeAddress>
      </ShipToTradeParty>
    </ApplicableHeaderTradeDelivery>
    <ApplicableHeaderTradeSettlement xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
      <InvoiceCurrencyCode xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">EUR</InvoiceCurrencyCode>
      <ApplicableTradeTax xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
        <CalculatedAmount xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">0.00</CalculatedAmount>
        <TypeCode xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">VAT</TypeCode>
        <ExemptionReason xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">Article 293 B du CGI</ExemptionReason>
        <BasisAmount xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$totalHT}</BasisAmount>
        <CategoryCode xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">E</CategoryCode>
        <RateApplicablePercent xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">0</RateApplicablePercent>
      </ApplicableTradeTax>
      <SpecifiedTradeSettlementHeaderMonetarySummation xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
        <LineTotalAmount xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$totalHT}</LineTotalAmount>
        <TaxBasisTotalAmount xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$totalHT}</TaxBasisTotalAmount>
        <TaxTotalAmount xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" currencyID="EUR">0.00</TaxTotalAmount>
        <GrandTotalAmount xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$totalTTC}</GrandTotalAmount>
        <DuePayableAmount xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$totalTTC}</DuePayableAmount>
      </SpecifiedTradeSettlementHeaderMonetarySummation>
      <InvoiceReferencedDocument xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
        <IssuerAssignedID xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">{$invoiceNumber}</IssuerAssignedID>
        <FormattedIssueDateTime xmlns="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
          <DateTimeString xmlns="urn:un:unece:uncefact:data:standard:QualifiedDataType:100" format="102">{$invoiceDate}</DateTimeString>
        </FormattedIssueDateTime>
      </InvoiceReferencedDocument>
    </ApplicableHeaderTradeSettlement>
  </SupplyChainTradeTransaction>
</CrossIndustryInvoice>
XML;
    }

    private function fetchAccessToken(string $clientId, string $clientSecret): string
    {
        $ch = curl_init(self::SUPERPDP_BASE_URL . '/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ]),
        ]);
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException(sprintf('OAuth token request failed (HTTP %d): %s', $httpCode, $result));
        }

        $data = json_decode($result, true);
        if (empty($data['access_token'])) {
            throw new \RuntimeException('OAuth response missing access_token: ' . $result);
        }

        return $data['access_token'];
    }

    private function fetchCompanyInfo(string $accessToken): array
    {
        $ch = curl_init(self::SUPERPDP_BASE_URL . '/v1.beta/companies/me');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ],
        ]);
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException(sprintf('GET /companies/me failed (HTTP %d): %s', $httpCode, $result));
        }

        $data = json_decode($result, true);
        if (empty($data['id'])) {
            throw new \RuntimeException('GET /companies/me response missing id field');
        }

        return [(int) $data['id'], (string) ($data['number'] ?? '')];
    }
}
