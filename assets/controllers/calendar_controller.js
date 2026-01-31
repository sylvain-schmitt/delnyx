import { Controller } from "@hotwired/stimulus";

/**
 * Calendrier visuel pour la prise de rendez-vous.
 * Permet de naviguer entre les mois et de sélectionner une date.
 */
export default class extends Controller {
    static targets = ["month", "grid", "prev", "next"];
    static values = {
        selectedDate: String,
        bookingUrl: String,
    };

    connect() {
        // Initialiser la date courante
        this.today = new Date();
        this.today.setHours(0, 0, 0, 0);

        // Date à afficher (mois courant)
        if (this.selectedDateValue) {
            this.displayDate = new Date(this.selectedDateValue + "T00:00:00");
        } else {
            this.displayDate = new Date();
        }
        this.displayDate.setDate(1);

        this.render();
    }

    previousMonth() {
        this.displayDate.setMonth(this.displayDate.getMonth() - 1);
        this.render();
    }

    nextMonth() {
        this.displayDate.setMonth(this.displayDate.getMonth() + 1);
        this.render();
    }

    render() {
        const year = this.displayDate.getFullYear();
        const month = this.displayDate.getMonth();

        // Formater le mois en français
        const monthName = this.displayDate.toLocaleDateString("fr-FR", {
            month: "long",
            year: "numeric",
        });
        this.monthTarget.textContent =
            monthName.charAt(0).toUpperCase() + monthName.slice(1);

        // Calculer les jours du mois
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);

        // Jour de la semaine du premier jour (0 = dimanche, 1 = lundi...)
        let startDay = firstDay.getDay();
        // Convertir pour que lundi = 0
        startDay = startDay === 0 ? 6 : startDay - 1;

        let html = "";

        // Cases vides avant le premier jour
        for (let i = 0; i < startDay; i++) {
            html += `<div class="p-2"></div>`;
        }

        // Jours du mois
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const currentDate = new Date(year, month, day);
            const dateStr = this.formatDate(currentDate);
            const isPast = currentDate < this.today;
            const isSelected = dateStr === this.selectedDateValue;
            const isToday = currentDate.getTime() === this.today.getTime();
            const isFutureTwoWeeks =
                currentDate >
                new Date(this.today.getTime() + 14 * 24 * 60 * 60 * 1000);

            let bgColor = "rgba(255,255,255,0.05)";
            let textColor = "#cbd5e1";
            let border = "none";
            let cursor = "pointer";
            let opacity = "1";
            let boxShadow = "none";

            if (isPast || isFutureTwoWeeks) {
                textColor = "#475569";
                cursor = "not-allowed";
                opacity = "0.5";
            } else if (isSelected) {
                bgColor = "#2563eb";
                textColor = "#ffffff";
                boxShadow = "0 4px 14px rgba(37, 99, 235, 0.4)";
                border = "2px solid #60a5fa";
            } else if (isToday) {
                bgColor = "rgba(6, 182, 212, 0.2)";
                textColor = "#22d3ee";
                border = "1px solid rgba(6, 182, 212, 0.5)";
            }

            const style = `width:26px;height:26px;margin:2px;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:500;transition:all 0.2s;background:${bgColor};color:${textColor};border:${border};cursor:${cursor};opacity:${opacity};box-shadow:${boxShadow};text-decoration:none;`;

            if (!isPast && !isFutureTwoWeeks) {
                html += `<a href="${this.bookingUrlValue}?date=${dateStr}" style="${style}">${day}</a>`;
            } else {
                html += `<div style="${style}">${day}</div>`;
            }
        }

        this.gridTarget.innerHTML = html;

        // Désactiver bouton précédent si on est sur le mois actuel
        const currentMonth = new Date();
        currentMonth.setDate(1);
        currentMonth.setHours(0, 0, 0, 0);
        const displayMonth = new Date(year, month, 1);

        if (displayMonth <= currentMonth) {
            this.prevTarget.classList.add("opacity-30", "pointer-events-none");
        } else {
            this.prevTarget.classList.remove(
                "opacity-30",
                "pointer-events-none"
            );
        }
    }

    formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
    }
}
