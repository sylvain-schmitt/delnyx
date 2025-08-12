
// Lazy load des particules
let scene,
camera,
renderer,
particles;

function initParticles() {
const canvas = document.getElementById('particles-canvas');
if (! canvas) 
return;



scene = new THREE.Scene();
camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
renderer = new THREE.WebGLRenderer({canvas: canvas, alpha: true});
renderer.setSize(window.innerWidth, window.innerHeight);

const geometry = new THREE.BufferGeometry();
const particleCount = window.innerWidth < 768 ? 60 : 120;
const positions = new Float32Array(particleCount * 3);
const colors = new Float32Array(particleCount * 3);

const color1 = new THREE.Color(0x0ea5e9);
const color2 = new THREE.Color(0x06b6d4);
const color3 = new THREE.Color(0x8b5cf6);

for (let i = 0; i < particleCount * 3; i += 3) {
positions[i] = (Math.random() - 0.5) * 20;
positions[i + 1] = (Math.random() - 0.5) * 20;
positions[i + 2] = (Math.random() - 0.5) * 20;

const colorChoice = Math.random();
let chosen = colorChoice < 0.33 ? color1 : (colorChoice < 0.66 ? color2 : color3);

colors[i] = chosen.r;
colors[i + 1] = chosen.g;
colors[i + 2] = chosen.b;
}

geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));

const material = new THREE.PointsMaterial({
size: window.innerWidth < 768 ? 0.05 : 0.02,
transparent: true,
opacity: 0.8,
vertexColors: true,
blending: THREE.AdditiveBlending
});

particles = new THREE.Points(geometry, material);
scene.add(particles);

camera.position.z = 5;
animateParticles();
}

function animateParticles() {
requestAnimationFrame(animateParticles);
if (particles) {
particles.rotation.x += 0.0008;
particles.rotation.y += 0.0012;
}
renderer.render(scene, camera);
}

const hero = document.querySelector('#accueil');
const observer = new IntersectionObserver((entries) => {
if (entries[0].isIntersecting) {
initParticles();
observer.disconnect();
}
}, {threshold: 0.1});
observer.observe(hero);