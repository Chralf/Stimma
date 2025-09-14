/**
 * Stimma Konfetti
 * En mycket enkel konfetti-implementation utan beroenden
 * Version 1.2
 */

// Använd IIFE för att undvika globala konflikter
const stimmaConfetti = (function() {
  'use strict';
  
  // Privata variabler för att hålla reda på aktiva konfetti-instanser
  let activeCanvas = null;
  let activeAnimationId = null;
  
  // Funktion för att skapa konfetti
  function createConfetti(options = {}) {
    // Avbryt eventuell pågående konfetti-animation
    if (activeAnimationId) {
      cancelAnimationFrame(activeAnimationId);
      if (activeCanvas && activeCanvas.parentNode) {
        activeCanvas.parentNode.removeChild(activeCanvas);
      }
    }
    
    // Standardvärden
    const defaults = {
      particleCount: 300,
      gravity: 0.5,
      spread: 100,
      startY: 0.8,
      direction: 'up',
      colors: ['#FF0000', '#00FF00', '#0000FF', '#FFFF00', '#FF00FF', '#00FFFF']
    };
    
    // Kombinera användarens alternativ med standardvärden
    const settings = Object.assign({}, defaults, options);
    
    // Skapa canvas
    const canvas = document.createElement('canvas');
    canvas.style.position = 'fixed';
    canvas.style.top = '0';
    canvas.style.left = '0';
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvas.style.pointerEvents = 'none';
    canvas.style.zIndex = '999999';
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    
    document.body.appendChild(canvas);
    activeCanvas = canvas;
    
    // Få 2D-kontext för canvas
    const ctx = canvas.getContext('2d');
    if (!ctx) {
      return;
    }
    
    // Definiera partikeltyper
    const particleTypes = [
      { type: 'confetti', chance: 0.7, minSize: 8, maxSize: 16, minSpeed: 15, maxSpeed: 25 },
      { type: 'star', chance: 0.15, minSize: 4, maxSize: 8, minSpeed: 18, maxSpeed: 30 },
      { type: 'glitter', chance: 0.15, minSize: 2, maxSize: 6, minSpeed: 20, maxSpeed: 35 }
    ];
    
    // Bestäm om vi ska ha multipla explosionspunkter
    const useMultipleOrigins = settings.spread > 100;
    const originCount = useMultipleOrigins ? 3 : 1;
    const originSpread = canvas.width * 0.4; // 40% av skärmbredden
    
    // Skapa partiklar
    const particles = [];
    for (let i = 0; i < settings.particleCount; i++) {
      // Bestäm vilken originpunkt denna partikel kommer från
      const originIndex = useMultipleOrigins ? Math.floor(Math.random() * originCount) : 0;
      const originOffset = useMultipleOrigins ? 
        (originIndex - (originCount-1)/2) * (originSpread / (originCount-1 || 1)) : 
        0;
      
      // Startposition - från knappen med variation
      const startY = canvas.height * settings.startY + (Math.random() * 20 - 10);
      const startX = canvas.width * 0.5 + originOffset + (Math.random() * 80 - 40);
      
      // Bestäm partikeltyp baserat på sannolikhet
      let selectedType = null;
      const rand = Math.random();
      let accumulatedChance = 0;
      
      for (const type of particleTypes) {
        accumulatedChance += type.chance;
        if (rand <= accumulatedChance) {
          selectedType = type;
          break;
        }
      }
      
      // Fallback om någon bug orsakar att ingen typ väljs
      if (!selectedType) selectedType = particleTypes[0];
      
      // Bestäm partikelns egenskaper
      const size = Math.random() * (selectedType.maxSize - selectedType.minSize) + selectedType.minSize;
      const initialSpeed = Math.random() * (selectedType.maxSpeed - selectedType.minSpeed) + selectedType.minSpeed;
      
      // Bestäm vinkel - se till att det är uppåt (mellan -80 och +80 grader från lodrätt uppåt)
      // I HTML Canvas: 0 grader = höger, 90 grader = nedåt, 180 grader = vänster, 270 grader = uppåt
      // Vi justerar för detta genom att sätta 270 som "rakt upp" och sprider därifrån
      let angle;
      if (selectedType.type === 'confetti') {
        // Konfetti har bred spridning
        angle = (Math.random() * (settings.spread * 1.2) - (settings.spread * 1.2)/2) + 270; // Bredare spridning
      } else if (selectedType.type === 'star') {
        // Stjärnor har medium spridning
        angle = (Math.random() * settings.spread - settings.spread/2) + 270; // Normal spridning
      } else {
        // Glitter har störst spridning
        angle = (Math.random() * (settings.spread * 1.5) - (settings.spread * 1.5)/2) + 270; // Mycket bred spridning
      }
      
      // Skapa partikel
      particles.push({
        x: startX,
        y: startY,
        size: size,
        type: selectedType.type,
        color: settings.colors[Math.floor(Math.random() * settings.colors.length)],
        angle: angle,
        speed: initialSpeed,
        initialSpeed: initialSpeed, // Spara ursprunglig hastighet
        gravity: settings.gravity * (1 + Math.random() * 0.5), // Lite variation i gravitationen
        rotation: Math.random() * 360,
        rotationSpeed: (Math.random() * 15) - 7.5,
        opacity: 1,
        fadeSpeed: 0.001 + Math.random() * 0.002,
        phase: 'shoot', // Första fasen: skjuta uppåt med hög hastighet
        // Bestäm hur långt uppåt partikeln ska åka (mellan 60% och 85% av vägen upp)
        targetHeight: canvas.height * (0.15 + Math.random() * 0.25)
      });
    }
    
    // Starta animationen
    let lastUpdate = Date.now();
    
    function animate() {
      // Beräkna tidsskillnad för jämn animation
      const now = Date.now();
      const delta = Math.min(30, now - lastUpdate) / 16.67; // Cappa till 30ms för att undvika stora hopp, normalisera till ~60fps
      lastUpdate = now;
      
      // Rensa canvas
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      
      let activeCount = 0;
      
      // Uppdatera och rita partiklar
      for (let i = 0; i < particles.length; i++) {
        const p = particles[i];
        
        // Konvertera vinkel till radianer och beräkna rörelsevektorer
        const angleRad = p.angle * Math.PI / 180;
        const vx = Math.cos(angleRad) * p.speed * delta;
        const vy = Math.sin(angleRad) * p.speed * delta;
        
        if (p.phase === 'shoot') {
          // Skjutfas: Rör sig uppåt enligt angiven vinkel
          p.x += vx;
          p.y += vy; // Observera att negativ vy är uppåt i HTML Canvas
          
          // Applicera gravitation för att sakta ner uppåtrörelsen
          p.speed -= p.gravity * delta * 0.2;
          
          // Kontrollera om partikeln har nått målhöjden eller stannat
          if (p.y <= p.targetHeight || p.speed <= 0) {
            // Mjuk övergång till fallfas
            if (!p.transitioning) {
              p.transitioning = true;
              p.transitionProgress = 0;
              p.originalAngle = p.angle;
              p.originalSpeed = p.speed;
              // Behåll fasen som 'shoot' under övergången
            } else {
              // Gradvis övergång från uppåt till nedåt
              p.transitionProgress += delta * 0.05; // Långsam övergång
              
              if (p.transitionProgress >= 1) {
                // Övergången är klar, byt till fallfas
                p.phase = 'fall';
                p.angle = -90; // Rakt nedåt
                p.speed = Math.random() * 1.5 + 0.5; // Låg starthastighet för fall
              } else {
                // Interpolera vinkel från originalAngle till -90 (nedåt)
                const progress = easeInOutCubic(p.transitionProgress);
                p.angle = p.originalAngle * (1 - progress) + (-90) * progress;
                
                // Sakta gradvis ner hastigheten tills övergången är klar
                p.speed = p.originalSpeed * (1 - progress) * 0.5;
              }
            }
          }
          
        } else if (p.phase === 'fall') {
          // Fallfas: Partikeln faller nedåt
          p.y -= vy; // Minus eftersom vy nu är negativ (nedåtriktad)
          
          // Mjukare horisontell rörelse under fall
          const wobbleAmount = Math.max(0, 1 - p.fadeSpeed * 1000); // Minskar wobble när partikeln bleknar
          p.x += Math.sin(now * 0.0008 + i * 0.4) * 0.4 * delta * wobbleAmount;
          
          // Gradvis ökning av fallhastighet med mjuk acceleration
          p.speed += p.gravity * delta * 0.03; // Långsammare acceleration
          
          // Begränsa maxhastigheten för fall för naturligare effekt
          p.speed = Math.min(p.speed, p.initialSpeed * 0.3);
          
          // Uppdatera rotation - långsammare rotation för mer realistisk effekt
          p.rotation += p.rotationSpeed * delta * 0.7;
          
          // Fade out när partikeln faller - gradvis ökande fade-hastighet
          const distanceFallen = Math.max(0, p.y - p.targetHeight);
          const fallProgress = Math.min(1, distanceFallen / (canvas.height - p.targetHeight));
          p.opacity -= (p.fadeSpeed + fallProgress * 0.002) * delta;
        }
        
        // Fortsätt om partikeln fortfarande är synlig och inom skärmen
        if (p.opacity > 0 && p.y < canvas.height) {
          activeCount++;
          
          // Rita partikeln
          ctx.save();
          ctx.translate(p.x, p.y);
          ctx.rotate(p.rotation * Math.PI / 180);
          ctx.globalAlpha = p.opacity;
          ctx.fillStyle = p.color;
          
          // Rita olika former baserat på partikeltyp
          if (p.type === 'star') {
            // Rita stjärna
            ctx.beginPath();
            ctx.arc(0, 0, p.size / 2, 0, Math.PI * 2);
            ctx.fill();
          } else if (p.type === 'glitter') {
            // Rita kvadrat för glitter
            ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);
          } else {
            // Rita rektangel för konfetti
            ctx.fillRect(-p.size / 2, -p.size / 4, p.size, p.size / 2);
          }
          
          ctx.restore();
        }
      }
      
      // Fortsätt animera eller avsluta
      if (activeCount > 0) {
        activeAnimationId = requestAnimationFrame(animate);
      } else {
        if (canvas.parentNode) {
          canvas.parentNode.removeChild(canvas);
          activeCanvas = null;
        }
        activeAnimationId = null;
      }
    }
    
    // Hjälpfunktioner för mjuk rörelse
    function easeInOutCubic(x) {
      return x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2;
    }
    
    // Starta animationsloopen
    activeAnimationId = requestAnimationFrame(animate);
    
    // Påskynda avslutningen efter en viss tid
    setTimeout(() => {
      // Om animationen fortfarande körs, öka blekningen för alla partiklar
      if (activeAnimationId) {
        for (let i = 0; i < particles.length; i++) {
          particles[i].fadeSpeed = 0.03;
        }
      }
    }, 3000);
  }
  
  // Exponera endast createConfetti-funktionen
  return {
    show: createConfetti
  };
})(); 