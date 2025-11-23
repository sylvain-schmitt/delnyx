# Tests Unitaires - Calculs Financiers

Ce dossier contient les tests unitaires pour valider tous les calculs financiers de l'application.

## Fichiers de tests

### `QuoteCalculationTest.php`
Tests pour les calculs de devis :
- Calcul du total HT avec TVA globale
- Calcul du total HT avec TVA par ligne
- Calcul du détail des taux de TVA
- Calcul sans TVA (micro-entreprise)

### `AmendmentCalculationTest.php`
Tests pour les calculs d'avenants :
- Calcul du total HT/TTC avec TVA globale
- Calcul du total HT/TTC avec TVA par ligne
- Calcul du Total TTC avec taux de TVA de l'avenant si ligne n'a pas de taux
- Calcul du delta TTC
- Calcul sans TVA
- Calcul du total corrigé du devis après avenant

### `AmendmentLineCalculationTest.php`
Tests pour les calculs des lignes d'avenant :
- Calcul du Total TTC avec différents taux de TVA (ligne, avenant, devis)
- Calcul du Total TTC pour modifications avec TVA par ligne/globale
- Calcul sans TVA
- Calcul du delta et delta TTC
- Cas spécifique : 4000€ HT avec 20% TVA = 4800€ TTC

## Exécution des tests

### Tous les tests unitaires
```bash
docker exec delnyx_app php bin/phpunit tests/Unit/
```

### Un fichier de tests spécifique
```bash
docker exec delnyx_app php bin/phpunit tests/Unit/QuoteCalculationTest.php
```

### Avec affichage détaillé (testdox)
```bash
docker exec delnyx_app php bin/phpunit tests/Unit/ --testdox
```

### Un test spécifique
```bash
docker exec delnyx_app php bin/phpunit tests/Unit/AmendmentLineCalculationTest.php --filter testSpecificCase4000HtWith20PercentTva
```

## Résultats attendus

Tous les tests doivent passer (18 tests, 46 assertions).

Si un test échoue, cela indique un problème dans les calculs financiers qui doit être corrigé.

## Ajout de nouveaux tests

Pour ajouter un nouveau test :

1. Créer une méthode publique commençant par `test`
2. Utiliser `$this->assertEquals()` pour vérifier les résultats
3. Documenter le test avec une description claire

Exemple :
```php
/**
 * Test : Description du test
 */
public function testMonNouveauTest(): void
{
    // Arrange : préparer les données
    $quote = new Quote();
    $quote->setTauxTVA('20.00');
    
    // Act : exécuter l'action
    $quote->recalculateTotalsFromLines();
    
    // Assert : vérifier le résultat
    $this->assertEquals('120.00', $quote->getMontantTTC(), 'Le montant TTC devrait être 120.00€');
}
```

