document.addEventListener("DOMContentLoaded", function () {
const phrases = ["Développeur Full-Stack Symfony", "Créateur de solutions sur-mesure"];
const typingElement = document.getElementById("typing");
let phraseIndex = 0;
let letterIndex = 0;
let deleting = false;

function typeLoop() {
const currentPhrase = phrases[phraseIndex];

if (! deleting) {
typingElement.textContent = currentPhrase.substring(0, letterIndex + 1);
letterIndex++;

if (letterIndex === currentPhrase.length) {
deleting = true;
setTimeout(typeLoop, 1500); // Pause avant suppression
return;
}
} else {
typingElement.textContent = currentPhrase.substring(0, letterIndex - 1);
letterIndex--;

if (letterIndex === 0) {
deleting = false;
phraseIndex = (phraseIndex + 1) % phrases.length;
}
}
setTimeout(typeLoop, deleting ? 50 : 80);
}

typeLoop();
});