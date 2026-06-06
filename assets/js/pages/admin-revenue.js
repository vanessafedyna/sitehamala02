(function () {
  var config = window.adminRevenueConfig || {};
  var chartData = config.chart || null;
  var moneyFormatter = new Intl.NumberFormat('fr-FR');
  var isCompactMobile = window.innerWidth <= 768;
  var isVerySmallMobile = window.innerWidth <= 430;

  var formatCountValue = function (value, type) {
    var numericValue = Number(value || 0);
    if (type === 'money') {
      return moneyFormatter.format(Math.round(numericValue)) + ' FCFA';
    }
    if (type === 'percent') {
      return moneyFormatter.format(Math.round(numericValue)) + '%';
    }
    return moneyFormatter.format(Math.round(numericValue));
  };

  var animateCountUp = function (element) {
    var target = Number(element.getAttribute('data-countup-value') || 0);
    var type = element.getAttribute('data-countup-type') || 'number';
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches || isVerySmallMobile;
    if (reducedMotion) {
      element.textContent = formatCountValue(target, type);
      return;
    }

    var duration = 900;
    var startTime = performance.now();

    var tick = function (now) {
      var progress = Math.min((now - startTime) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      element.textContent = formatCountValue(target * eased, type);
      if (progress < 1) {
        window.requestAnimationFrame(tick);
      }
    };

    window.requestAnimationFrame(tick);
  };

  var setupCountUps = function () {
    var countNodes = document.querySelectorAll('[data-countup-value]');
    if (!countNodes.length) {
      return;
    }

    var observer = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) {
          return;
        }

        animateCountUp(entry.target);
        obs.unobserve(entry.target);
      });
    }, { threshold: 0.55 });

    countNodes.forEach(function (node) {
      observer.observe(node);
    });
  };

  var setupRevealAnimations = function () {
    var revealNodes = document.querySelectorAll('.admin-revenue-reveal');
    if (!revealNodes.length) {
      return;
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || isCompactMobile) {
      revealNodes.forEach(function (node) {
        node.classList.add('is-visible');
        node.style.transitionDelay = '0ms';
      });
      return;
    }

    var revealObserver = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) {
          return;
        }

        entry.target.classList.add('is-visible');
        obs.unobserve(entry.target);
      });
    }, { threshold: 0.14 });

    revealNodes.forEach(function (node, index) {
      node.style.transitionDelay = Math.min(index * 45, 220) + 'ms';
      revealObserver.observe(node);
    });
  };

  var setupStickyToolbar = function () {
    var toolbar = document.querySelector('.admin-revenue-toolbar');
    if (!toolbar || isCompactMobile) {
      return;
    }

    var initialTop = toolbar.getBoundingClientRect().top + window.scrollY;
    var toggleState = function () {
      var trigger = window.scrollY > Math.max(initialTop - 14, 0);
      toolbar.classList.toggle('is-stuck', trigger);
    };

    toggleState();
    window.addEventListener('scroll', toggleState, { passive: true });
  };

  var setupDetailsEnhancement = function () {
    var detail = document.querySelector('.admin-revenue-details');
    if (!detail) {
      return;
    }

    var summary = detail.querySelector('summary');
    if (!summary) {
      return;
    }

    summary.setAttribute('aria-expanded', detail.open ? 'true' : 'false');
    detail.addEventListener('toggle', function () {
      summary.setAttribute('aria-expanded', detail.open ? 'true' : 'false');
    });
  };

  var setupFilterSync = function () {
    var presetField = document.getElementById('preset');
    var dateFromField = document.getElementById('date_from');
    var dateToField = document.getElementById('date_to');
    if (!presetField || !dateFromField || !dateToField) {
      return;
    }

    var syncBounds = function () {
      dateFromField.max = dateToField.value || '';
      dateToField.min = dateFromField.value || '';
    };

    var switchToCustom = function () {
      if (presetField.value !== 'custom') {
        presetField.value = 'custom';
      }
      syncBounds();
    };

    syncBounds();
    dateFromField.addEventListener('change', switchToCustom);
    dateToField.addEventListener('change', switchToCustom);
    presetField.addEventListener('change', syncBounds);
  };

  var setupChart = function () {
    var canvas = document.getElementById('revenueChart');
    if (!canvas || !chartData || typeof Chart === 'undefined') {
      return;
    }

    var labels = Array.isArray(chartData.labels) ? chartData.labels : [];
    var revenueData = Array.isArray(chartData.revenue) ? chartData.revenue : [];
    var ordersData = Array.isArray(chartData.orders) ? chartData.orders : [];

    new Chart(canvas, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'CA mensuel',
          data: revenueData,
          backgroundColor: 'rgba(31, 122, 79, 0.74)',
          borderColor: '#1f7a4f',
          borderWidth: 1,
          borderRadius: 10,
          borderSkipped: false,
          barPercentage: isCompactMobile ? 0.64 : 0.72,
          categoryPercentage: isCompactMobile ? 0.7 : 0.76,
          hoverBackgroundColor: 'rgba(31, 122, 79, 0.78)'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
          duration: 0
        },
        interaction: {
          intersect: false,
          mode: 'index'
        },
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            backgroundColor: 'rgba(14, 11, 8, 0.94)',
            titleColor: '#ffffff',
            bodyColor: '#f7f3eb',
            borderColor: 'rgba(31, 122, 79, 0.28)',
            borderWidth: 1,
            padding: isCompactMobile ? 10 : 12,
            displayColors: false,
            callbacks: {
              label: function (context) {
                return 'CA : ' + moneyFormatter.format(Number(context.parsed.y || 0)) + ' FCFA';
              },
              afterLabel: function (context) {
                var orders = Number(ordersData[context.dataIndex] || 0);
                return 'Commandes : ' + moneyFormatter.format(orders);
              }
            }
          }
        },
        scales: {
          x: {
            grid: {
              display: false,
              drawBorder: false
            },
            ticks: {
              color: 'rgba(14, 11, 8, 0.72)',
              font: {
                size: isCompactMobile ? 9 : 11,
                weight: '600'
              },
              maxRotation: 0,
              autoSkipPadding: isCompactMobile ? 8 : 14
            },
            border: {
              display: false
            }
          },
          y: {
            beginAtZero: true,
            grace: '8%',
            ticks: {
              color: 'rgba(14, 11, 8, 0.62)',
              font: {
                size: isCompactMobile ? 9 : 11
              },
              padding: isCompactMobile ? 6 : 8,
              callback: function (value) {
                return moneyFormatter.format(Number(value || 0)) + ' FCFA';
              }
            },
            grid: {
              color: 'rgba(31, 122, 79, 0.10)',
              drawBorder: false
            },
            border: {
              display: false
            }
          }
        },
        layout: {
          padding: {
            top: 4,
            right: 4,
            bottom: 0,
            left: 0
          }
        }
      }
    });
  };

  setupChart();
  setupCountUps();
  setupRevealAnimations();
  setupStickyToolbar();
  setupDetailsEnhancement();
  setupFilterSync();
})();
