// assets/js/pages/accueil.js — Newsletter (DB-driven) + carousel

document.addEventListener('DOMContentLoaded', function () {
  const baseUrl = document.body?.dataset?.baseUrl || '/';

  // Newsletter form submission
  const newsletterForm = document.getElementById('newsletterForm');
  if (newsletterForm) {
    newsletterForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const email = String(document.getElementById('newsletterEmail')?.value || '').trim().toLowerCase();

      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert('Veuillez saisir un email valide.');
        return;
      }

      try {
        const res = await fetch(`${baseUrl}public/api/newsletter_subscribe.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ email })
        });
        const json = await res.json().catch(() => null);
        if (!json || !json.ok) {
          alert((json && json.message) ? json.message : "Impossible de s'inscrire.");
          return;
        }

        alert(json.message || 'Merci pour votre inscription !');
        newsletterForm.reset();
      } catch (err) {
        alert("Impossible de s'inscrire pour le moment. Veuillez réessayer.");
      }
    });
  }

  // Testimonial carousel (simple version)
  let currentTestimonial = 0;
  const testimonialCards = document.querySelectorAll('.testimonial-card');

  if (testimonialCards.length > 0) {
    function showTestimonial(index) {
      testimonialCards.forEach((card, i) => {
        card.style.display = i === index ? 'block' : 'none';
      });
    }

    // Show all testimonials on desktop, cycle on mobile
    if (window.innerWidth < 768) {
      showTestimonial(0);

      // Auto-rotate testimonials every 5 seconds
      setInterval(() => {
        currentTestimonial = (currentTestimonial + 1) % testimonialCards.length;
        showTestimonial(currentTestimonial);
      }, 5000);
    } else {
      // Show all testimonials on desktop
      testimonialCards.forEach(card => {
        card.style.display = 'block';
      });
    }
  }
});

