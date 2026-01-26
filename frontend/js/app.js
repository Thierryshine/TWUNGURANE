/**
 * TWUNGURANE - Application principale
 * Gestion des groupes d'épargne communautaire
 * Design inspiré de Kaydmaal - Fintech moderne
 */

// État de l'application
let currentUser = null;
let currentCircleId = null;
let otpCode = null;
let otpPhone = null;
let charts = {};

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    initApp();
});

/**
 * Initialisation de l'application
 */
function initApp() {
    // Vérifier si l'utilisateur est connecté
    const savedUser = localStorage.getItem('twungurane_user');
    if (savedUser) {
        currentUser = JSON.parse(savedUser);
        showMainApp();
    } else {
        // Afficher la landing page par défaut
        showLandingPage();
    }

    // Événements de la landing page
    setupLandingEvents();

    // Événements d'authentification
    setupAuthEvents();

    // Événements de navigation
    setupNavigationEvents();

    // Événements des pages
    setupPageEvents();

    // Menu mobile
    setupMobileMenu();

    // Liens du footer
    setupFooterLinks();

    // Scroll reveal animations
    setupScrollReveal();
}

/**
 * Configuration des événements de la landing page
 */
function setupLandingEvents() {
    // Navigation scroll effect
    const landingNav = document.getElementById('landingNav');
    if (landingNav) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                landingNav.classList.add('scrolled');
            } else {
                landingNav.classList.remove('scrolled');
            }
        });
    }

    // Landing mobile menu toggle
    const landingNavToggle = document.getElementById('landingNavToggle');
    const landingNavMenu = document.getElementById('landingNavMenu');
    if (landingNavToggle && landingNavMenu) {
        landingNavToggle.addEventListener('click', () => {
            landingNavToggle.classList.toggle('active');
            landingNavMenu.classList.toggle('active');
        });

        // Close menu when clicking a link
        document.querySelectorAll('.landing-nav-link').forEach(link => {
            link.addEventListener('click', () => {
                landingNavToggle.classList.remove('active');
                landingNavMenu.classList.remove('active');
            });
        });
    }

    // Smooth scroll for anchor links
    document.querySelectorAll('.landing-page a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId && targetId !== '#' && !this.hasAttribute('data-no-scroll')) {
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    e.preventDefault();
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });

    // CTA buttons to show auth page
    const btnShowLogin = document.getElementById('btnShowLogin');
    const btnShowRegister = document.getElementById('btnShowRegister');
    const btnShowLoginMobile = document.getElementById('btnShowLoginMobile');
    const btnShowRegisterMobile = document.getElementById('btnShowRegisterMobile');
    const heroGetStarted = document.getElementById('heroGetStarted');
    const dashboardCta = document.getElementById('dashboardCta');

    // Desktop login button
    if (btnShowLogin) {
        btnShowLogin.addEventListener('click', (e) => {
            e.preventDefault();
            showAuthPage();
            showAuthForm('login');
        });
    }

    // Mobile login button
    if (btnShowLoginMobile) {
        btnShowLoginMobile.addEventListener('click', (e) => {
            e.preventDefault();
            // Close the mobile menu first
            const landingNavToggle = document.getElementById('landingNavToggle');
            const landingNavMenu = document.getElementById('landingNavMenu');
            if (landingNavToggle) landingNavToggle.classList.remove('active');
            if (landingNavMenu) landingNavMenu.classList.remove('active');
            showAuthPage();
            showAuthForm('login');
        });
    }

    // Desktop register button
    if (btnShowRegister) {
        btnShowRegister.addEventListener('click', (e) => {
            e.preventDefault();
            showAuthPage();
            showAuthForm('register');
        });
    }

    // Mobile register button
    if (btnShowRegisterMobile) {
        btnShowRegisterMobile.addEventListener('click', (e) => {
            e.preventDefault();
            // Close the mobile menu first
            const landingNavToggle = document.getElementById('landingNavToggle');
            const landingNavMenu = document.getElementById('landingNavMenu');
            if (landingNavToggle) landingNavToggle.classList.remove('active');
            if (landingNavMenu) landingNavMenu.classList.remove('active');
            showAuthPage();
            showAuthForm('register');
        });
    }

    if (heroGetStarted) {
        heroGetStarted.addEventListener('click', (e) => {
            e.preventDefault();
            showAuthPage();
            showAuthForm('register');
        });
    }

    if (dashboardCta) {
        dashboardCta.addEventListener('click', (e) => {
            e.preventDefault();
            if (currentUser) {
                showMainApp();
            } else {
                showAuthPage();
                showAuthForm('login');
            }
        });
    }

    // Back to landing links
    const backToLanding = document.getElementById('backToLanding');
    const backToLandingReg = document.getElementById('backToLandingReg');

    if (backToLanding) {
        backToLanding.addEventListener('click', (e) => {
            e.preventDefault();
            showLandingPage();
        });
    }

    if (backToLandingReg) {
        backToLandingReg.addEventListener('click', (e) => {
            e.preventDefault();
            showLandingPage();
        });
    }

    // Partner form submission
    const partnerForm = document.getElementById('partnerForm');
    if (partnerForm) {
        partnerForm.addEventListener('submit', handlePartnerFormSubmit);
    }
}

/**
 * Gérer la soumission du formulaire de partenariat
 */
function handlePartnerFormSubmit(e) {
    e.preventDefault();
    
    const formData = {
        organization: document.getElementById('partnerOrg').value,
        name: document.getElementById('partnerName').value,
        email: document.getElementById('partnerEmail').value,
        phone: document.getElementById('partnerPhone').value,
        type: document.getElementById('partnerType').value,
        message: document.getElementById('partnerMessage').value
    };

    // Validation
    if (!validateEmail(formData.email)) {
        showNotification('Adresse email invalide', 'error');
        return;
    }

    // Simulation d'envoi
    showNotification('Demande de partenariat envoyée avec succès ! Nous vous contacterons sous 24 heures.', 'success');
    e.target.reset();
}

/**
 * Afficher une notification toast
 */
function showNotification(message, type = 'info') {
    // Créer ou récupérer le conteneur de notifications
    let container = document.getElementById('notificationToast');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notificationToast';
        container.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        `;
        document.body.appendChild(container);
    }

    const colors = {
        success: { bg: '#10B981', icon: 'fa-check-circle' },
        error: { bg: '#EF4444', icon: 'fa-exclamation-circle' },
        warning: { bg: '#F59E0B', icon: 'fa-exclamation-triangle' },
        info: { bg: '#3B82F6', icon: 'fa-info-circle' }
    };

    const config = colors[type] || colors.info;

    const toast = document.createElement('div');
    toast.style.cssText = `
        background: ${config.bg};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideInRight 0.4s ease;
        max-width: 400px;
        font-weight: 500;
    `;
    toast.innerHTML = `
        <i class="fas ${config.icon}"></i>
        <span>${message}</span>
    `;

    container.appendChild(toast);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.4s ease forwards';
        setTimeout(() => toast.remove(), 400);
    }, 5000);
}

/**
 * Afficher la landing page
 */
function showLandingPage() {
    // Hide all pages and app elements
    document.getElementById('authPage').classList.remove('active');
    document.getElementById('navbar').style.display = 'none';
    document.querySelector('.footer')?.classList.remove('visible');
    document.querySelectorAll('.page:not(#authPage)').forEach(page => {
        page.classList.remove('active');
    });

    // Show landing page
    const landingPage = document.getElementById('landingPage');
    if (landingPage) {
        landingPage.classList.add('active');
        window.scrollTo(0, 0);
    }
}

/**
 * Setup scroll reveal animations
 */
function setupScrollReveal() {
    const revealElements = document.querySelectorAll('.feature-card, .step-card, .stat-item, .value-card, .about-card, .partner-type, .coverage-feature, .benefit-item');
    
    revealElements.forEach((el, index) => {
        el.classList.add('reveal');
        el.style.transitionDelay = `${index % 4 * 0.1}s`;
    });

    const revealOnScroll = () => {
        revealElements.forEach(el => {
            const elementTop = el.getBoundingClientRect().top;
            const windowHeight = window.innerHeight;
            
            if (elementTop < windowHeight - 100) {
                el.classList.add('active');
            }
        });
    };

    window.addEventListener('scroll', revealOnScroll);
    revealOnScroll(); // Initial check

    // Counter animation for stats
    setupCounterAnimation();
}

/**
 * Animate number counters
 */
function setupCounterAnimation() {
    const statItems = document.querySelectorAll('.stats-section .stat-info h3');
    let animated = false;

    const animateCounters = () => {
        const statsSection = document.querySelector('.stats-section');
        if (!statsSection || animated) return;

        const sectionTop = statsSection.getBoundingClientRect().top;
        const windowHeight = window.innerHeight;

        if (sectionTop < windowHeight - 100) {
            animated = true;
            statItems.forEach(stat => {
                const text = stat.textContent;
                const match = text.match(/(\d+)/);
                if (match) {
                    const targetNum = parseInt(match[0]);
                    const suffix = text.replace(match[0], '');
                    animateValue(stat, 0, targetNum, 2000, suffix);
                }
            });
        }
    };

    window.addEventListener('scroll', animateCounters);
    animateCounters(); // Initial check
}

/**
 * Animate a value from start to end
 */
function animateValue(element, start, end, duration, suffix = '') {
    const startTime = performance.now();
    
    const update = (currentTime) => {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Easing function (ease out quad)
        const easeProgress = 1 - Math.pow(1 - progress, 3);
        
        const currentValue = Math.floor(start + (end - start) * easeProgress);
        element.textContent = currentValue + suffix;
        
        if (progress < 1) {
            requestAnimationFrame(update);
        }
    };
    
    requestAnimationFrame(update);
}

/**
 * Configuration des événements d'authentification
 */
function setupAuthEvents() {
    // Inscription
    document.getElementById('registerFormElement').addEventListener('submit', handleRegister);
    document.getElementById('showRegister').addEventListener('click', (e) => {
        e.preventDefault();
        showAuthForm('register');
    });

    // Connexion
    document.getElementById('loginFormElement').addEventListener('submit', handleLogin);
    document.getElementById('showLogin').addEventListener('click', (e) => {
        e.preventDefault();
        showAuthForm('login');
    });

    // OTP
    document.getElementById('otpFormElement').addEventListener('submit', handleOTP);
    document.getElementById('resendOTP').addEventListener('click', (e) => {
        e.preventDefault();
        sendOTP(otpPhone);
    });
}

/**
 * Gestion de l'inscription
 */
function handleRegister(e) {
    e.preventDefault();
    const formData = {
        name: document.getElementById('regName').value,
        phone: document.getElementById('regPhone').value,
        email: document.getElementById('regEmail').value,
        password: document.getElementById('regPassword').value,
        role: document.getElementById('regRole').value
    };

    // Validation
    if (!validatePhone(formData.phone)) {
        alert('Numéro de téléphone invalide. Format: +257 XX XX XX XX');
        return;
    }

    if (!validateEmail(formData.email)) {
        alert('Adresse email invalide');
        return;
    }

    // Vérifier si l'utilisateur existe déjà
    const existingUser = UserService.findByIdentifier(formData.phone) || 
                        UserService.findByIdentifier(formData.email);
    if (existingUser) {
        alert('Un compte existe déjà avec ce téléphone ou cet email');
        return;
    }

    // Envoyer OTP
    sendOTP(formData.phone);
    otpPhone = formData.phone;
    
    // Stocker temporairement les données d'inscription
    sessionStorage.setItem('pendingRegistration', JSON.stringify(formData));
}

/**
 * Gestion de la connexion
 */
function handleLogin(e) {
    e.preventDefault();
    const identifier = document.getElementById('loginIdentifier').value;
    const password = document.getElementById('loginPassword').value;

    const user = UserService.findByIdentifier(identifier);
    if (!user || user.password !== password) {
        alert('Identifiants incorrects');
        return;
    }

    // Envoyer OTP
    sendOTP(user.phone);
    otpPhone = user.phone;
    sessionStorage.setItem('pendingLogin', JSON.stringify({ identifier, password }));
}

/**
 * Gestion de l'OTP
 */
function handleOTP(e) {
    e.preventDefault();
    const enteredOTP = document.getElementById('otpCode').value;

    if (enteredOTP !== otpCode) {
        alert('Code OTP incorrect');
        return;
    }

    // Vérifier s'il s'agit d'une inscription ou d'une connexion
    const pendingRegistration = sessionStorage.getItem('pendingRegistration');
    const pendingLogin = sessionStorage.getItem('pendingLogin');

    if (pendingRegistration) {
        // Finaliser l'inscription
        const formData = JSON.parse(pendingRegistration);
        const user = UserService.create(formData);
        currentUser = user;
        localStorage.setItem('twungurane_user', JSON.stringify(user));
        sessionStorage.removeItem('pendingRegistration');
        showMainApp();
    } else if (pendingLogin) {
        // Finaliser la connexion
        const loginData = JSON.parse(pendingLogin);
        const user = UserService.findByIdentifier(loginData.identifier);
        currentUser = user;
        localStorage.setItem('twungurane_user', JSON.stringify(user));
        sessionStorage.removeItem('pendingLogin');
        showMainApp();
    }
}

/**
 * Envoi d'OTP (simulation)
 */
function sendOTP(phone) {
    otpCode = generateOTP();
    document.getElementById('otpPhone').textContent = phone;
    showAuthForm('otp');
    alert(`Code OTP simulé: ${otpCode}\n(En production, ce code serait envoyé par SMS)`);
}

/**
 * Afficher le formulaire d'authentification
 */
function showAuthForm(form) {
    document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
    if (form === 'login') {
        document.getElementById('loginForm').classList.add('active');
    } else if (form === 'register') {
        document.getElementById('registerForm').classList.add('active');
    } else if (form === 'otp') {
        document.getElementById('otpForm').classList.add('active');
    }
}

/**
 * Afficher la page d'authentification
 */
function showAuthPage() {
    // Hide landing page
    const landingPage = document.getElementById('landingPage');
    if (landingPage) {
        landingPage.classList.remove('active');
    }

    // Show auth page
    document.getElementById('authPage').classList.add('active');
    document.getElementById('navbar').style.display = 'none';
    document.querySelector('.footer')?.classList.remove('visible');
    document.querySelectorAll('.page:not(#authPage)').forEach(page => {
        page.classList.remove('active');
    });
    
    window.scrollTo(0, 0);
}

/**
 * Afficher l'application principale
 */
function showMainApp() {
    // Hide landing page
    const landingPage = document.getElementById('landingPage');
    if (landingPage) {
        landingPage.classList.remove('active');
    }

    document.getElementById('authPage').classList.remove('active');
    document.getElementById('navbar').style.display = 'block';
    document.getElementById('userName').textContent = currentUser.name;
    document.querySelector('.footer')?.classList.add('visible');
    showPage('dashboard');
    window.scrollTo(0, 0);
}

/**
 * Configuration des événements de navigation
 */
function setupNavigationEvents() {
    // Navigation par liens
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const page = link.getAttribute('data-page');
            if (page) {
                showPage(page);
            }
        });
    });

    // Déconnexion
    document.getElementById('btnLogout').addEventListener('click', () => {
        if (confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
            currentUser = null;
            localStorage.removeItem('twungurane_user');
            showAuthPage();
            showAuthForm('login');
        }
    });
}

/**
 * Afficher une page
 */
function showPage(pageName) {
    // Masquer toutes les pages
    document.querySelectorAll('.page').forEach(page => {
        page.classList.remove('active');
    });

    // Afficher la page demandée
    const pageElement = document.getElementById(`${pageName}Page`);
    if (pageElement) {
        pageElement.classList.add('active');
        
        // Charger les données de la page
        loadPageData(pageName);
    }
}

/**
 * Charger les données d'une page
 */
function loadPageData(pageName) {
    switch(pageName) {
        case 'dashboard':
            loadDashboard();
            break;
        case 'cercles':
            loadCircles();
            break;
        case 'transactions':
            loadTransactions();
            break;
        case 'prets':
            loadLoans();
            break;
        case 'rapports':
            loadReports();
            break;
        case 'contact':
            // Pas de chargement nécessaire
            break;
    }
}

/**
 * Charger le tableau de bord
 */
function loadDashboard() {
    const userCircles = CircleService.findByUserId(currentUser.id);
    const transactions = TransactionService.findByUserId(currentUser.id);
    
    // Calculer les statistiques
    let totalBalance = 0;
    let monthlyContributions = 0;
    let activeMembersCount = 0;
    
    userCircles.forEach(circle => {
        totalBalance += circle.totalBalance || 0;
        const circleMembers = MemberService.findByCircleId(circle.id);
        activeMembersCount += circleMembers.filter(m => m.status === 'active').length;
    });

    const currentMonth = new Date().getMonth();
    const currentYear = new Date().getFullYear();
    monthlyContributions = transactions
        .filter(t => {
            const date = new Date(t.date);
            return date.getMonth() === currentMonth && 
                   date.getFullYear() === currentYear &&
                   t.type === 'epargne';
        })
        .reduce((sum, t) => sum + t.amount, 0);

    // Mettre à jour l'interface
    document.getElementById('totalBalance').textContent = formatAmount(totalBalance);
    document.getElementById('activeMembers').textContent = activeMembersCount;
    document.getElementById('monthlyContributions').textContent = formatAmount(monthlyContributions);
    document.getElementById('myCircles').textContent = userCircles.length;
    document.getElementById('dashboardGreeting').textContent = 
        `Bienvenue, ${currentUser.name} !`;

    // Graphiques
    renderDashboardCharts(userCircles, transactions);

    // Notifications
    loadNotifications();
}

/**
 * Rendre les graphiques du tableau de bord
 */
function renderDashboardCharts(circles, transactions) {
    // Graphique d'évolution des contributions
    const contributionsCtx = document.getElementById('contributionsChart');
    if (contributionsCtx) {
        if (charts.contributions) {
            charts.contributions.destroy();
        }

        // Grouper par mois
        const monthlyData = {};
        transactions
            .filter(t => t.type === 'epargne')
            .forEach(t => {
                const date = new Date(t.date);
                const monthKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
                monthlyData[monthKey] = (monthlyData[monthKey] || 0) + t.amount;
            });

        const labels = Object.keys(monthlyData).sort();
        const data = labels.map(key => monthlyData[key]);

        charts.contributions = new Chart(contributionsCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Contributions (FBU)',
                    data: data,
                    borderColor: '#00A859',
                    backgroundColor: 'rgba(0, 168, 89, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

    // Graphique de répartition par cercle
    const circlesCtx = document.getElementById('circlesChart');
    if (circlesCtx) {
        if (charts.circles) {
            charts.circles.destroy();
        }

        const labels = circles.map(c => c.name);
        const data = circles.map(c => c.totalBalance || 0);

        charts.circles = new Chart(circlesCtx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: [
                        '#00A859',
                        '#00C96B',
                        '#008045',
                        '#FFD700',
                        '#FFA500'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true
            }
        });
    }
}

/**
 * Charger les notifications
 */
function loadNotifications() {
    const notifications = NotificationService.findByUserId(currentUser.id);
    const container = document.getElementById('notificationsList');
    
    if (notifications.length === 0) {
        container.innerHTML = '<div class="empty-state"><p>Aucune notification</p></div>';
        return;
    }

    container.innerHTML = notifications.slice(0, 5).map(n => `
        <div class="notification-item ${n.type || ''}">
            <h4>${n.title}</h4>
            <p>${n.message}</p>
            <small>${formatDate(n.createdAt)}</small>
        </div>
    `).join('');
}

/**
 * Configuration des événements des pages
 */
function setupPageEvents() {
    // Cercles
    setupCircleEvents();
    
    // Transactions
    setupTransactionEvents();
    
    // Prêts
    setupLoanEvents();
    
    // Rapports
    setupReportEvents();
    
    // Contact
    setupContactEvents();
}

/**
 * Configuration des événements des cercles
 */
function setupCircleEvents() {
    // Créer un cercle
    document.getElementById('btnCreateCircle').addEventListener('click', () => {
        openCircleModal();
    });

    // Formulaire de cercle
    document.getElementById('circleForm').addEventListener('submit', handleCircleSubmit);
    document.getElementById('closeCircleModal').addEventListener('click', closeCircleModal);
    document.getElementById('cancelCircleForm').addEventListener('click', closeCircleModal);

    // Retour à la liste
    document.getElementById('backToCircles').addEventListener('click', () => {
        showPage('cercles');
    });

    // Ajouter un membre
    document.getElementById('btnAddMember').addEventListener('click', () => {
        openMemberModal();
    });

    // Formulaire de membre
    document.getElementById('memberForm').addEventListener('submit', handleMemberSubmit);
    document.getElementById('closeMemberModal').addEventListener('click', closeMemberModal);
    document.getElementById('cancelMemberForm').addEventListener('click', closeMemberModal);
}

/**
 * Charger les cercles
 */
function loadCircles() {
    const circles = CircleService.findByUserId(currentUser.id);
    const container = document.getElementById('circlesList');
    
    if (circles.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-circle"></i>
                <h3>Aucun cercle</h3>
                <p>Créez votre premier cercle d'épargne pour commencer</p>
            </div>
        `;
        return;
    }

    container.innerHTML = circles.map(circle => `
        <div class="circle-card" onclick="viewCircleDetails('${circle.id}')">
            <div class="circle-card-header">
                <h3>${circle.name}</h3>
                <span class="circle-badge">${circle.type}</span>
            </div>
            <div class="circle-card-info">
                <i class="fas fa-map-marker-alt"></i>
                ${circle.province}, ${circle.commune}
            </div>
            <div class="circle-card-info">
                <i class="fas fa-users"></i>
                ${(circle.members || []).length} / ${circle.maxMembers} membres
            </div>
            <div class="circle-card-info">
                <i class="fas fa-wallet"></i>
                Solde: ${formatAmount(circle.totalBalance || 0)}
            </div>
            <div class="circle-card-info">
                <i class="fas fa-calendar"></i>
                Contribution: ${formatAmount(circle.amount)} / ${circle.frequency}
            </div>
        </div>
    `).join('');
}

/**
 * Ouvrir le modal de cercle
 */
function openCircleModal(circleId = null) {
    const modal = document.getElementById('circleModal');
    const form = document.getElementById('circleForm');
    const title = document.getElementById('circleModalTitle');
    
    if (circleId) {
        // Mode édition
        const circle = CircleService.findById(circleId);
        if (circle) {
            title.textContent = 'Modifier le cercle';
            document.getElementById('circleName').value = circle.name;
            document.getElementById('circleType').value = circle.type;
            document.getElementById('circleProvince').value = circle.province;
            document.getElementById('circleCommune').value = circle.commune;
            document.getElementById('circleAmount').value = circle.amount;
            document.getElementById('circleFrequency').value = circle.frequency;
            document.getElementById('circleDuration').value = circle.duration;
            document.getElementById('circleMaxMembers').value = circle.maxMembers;
            form.dataset.circleId = circleId;
        }
    } else {
        // Mode création
        title.textContent = 'Créer un cercle';
        form.reset();
        delete form.dataset.circleId;
    }
    
    modal.classList.add('active');
}

/**
 * Fermer le modal de cercle
 */
function closeCircleModal() {
    document.getElementById('circleModal').classList.remove('active');
    document.getElementById('circleForm').reset();
}

/**
 * Gérer la soumission du formulaire de cercle
 */
function handleCircleSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const circleId = form.dataset.circleId;

    const circleData = {
        name: document.getElementById('circleName').value,
        type: document.getElementById('circleType').value,
        province: document.getElementById('circleProvince').value,
        commune: document.getElementById('circleCommune').value,
        amount: parseFloat(document.getElementById('circleAmount').value),
        frequency: document.getElementById('circleFrequency').value,
        duration: parseInt(document.getElementById('circleDuration').value),
        maxMembers: parseInt(document.getElementById('circleMaxMembers').value),
        members: []
    };

    if (circleId) {
        CircleService.update(circleId, circleData);
        alert('Cercle modifié avec succès');
    } else {
        const newCircle = CircleService.create(circleData);
        // Ajouter le créateur comme membre admin
        MemberService.addToCircle(newCircle.id, {
            userId: currentUser.id,
            firstName: currentUser.name.split(' ')[0],
            lastName: currentUser.name.split(' ').slice(1).join(' ') || '',
            phone: currentUser.phone,
            role: 'admin'
        });
        alert('Cercle créé avec succès');
    }

    closeCircleModal();
    loadCircles();
    if (currentCircleId) {
        viewCircleDetails(currentCircleId);
    }
}

/**
 * Voir les détails d'un cercle
 */
function viewCircleDetails(circleId) {
    currentCircleId = circleId;
    const circle = CircleService.findById(circleId);
    if (!circle) return;

    // Mettre à jour le titre
    document.getElementById('circleDetailsTitle').textContent = circle.name;

    // Informations du cercle
    const infoContent = document.getElementById('circleInfoContent');
    infoContent.innerHTML = `
        <div class="circle-card-info">
            <i class="fas fa-tag"></i>
            <strong>Type:</strong> ${circle.type}
        </div>
        <div class="circle-card-info">
            <i class="fas fa-map-marker-alt"></i>
            <strong>Localisation:</strong> ${circle.province}, ${circle.commune}
        </div>
        <div class="circle-card-info">
            <i class="fas fa-wallet"></i>
            <strong>Solde total:</strong> ${formatAmount(circle.totalBalance || 0)}
        </div>
        <div class="circle-card-info">
            <i class="fas fa-money-bill-wave"></i>
            <strong>Contribution:</strong> ${formatAmount(circle.amount)} / ${circle.frequency}
        </div>
        <div class="circle-card-info">
            <i class="fas fa-calendar-alt"></i>
            <strong>Durée:</strong> ${circle.duration} mois
        </div>
    `;

    // Membres
    const members = MemberService.findByCircleId(circleId);
    document.getElementById('membersCount').textContent = members.length;
    const membersContainer = document.getElementById('membersList');
    
    if (members.length === 0) {
        membersContainer.innerHTML = '<div class="empty-state"><p>Aucun membre</p></div>';
    } else {
        membersContainer.innerHTML = members.map(member => {
            const initials = `${member.firstName[0]}${member.lastName[0]}`.toUpperCase();
            return `
                <div class="member-card">
                    <div class="member-avatar">${initials}</div>
                    <div class="member-info">
                        <h4>${member.firstName} ${member.lastName}</h4>
                        <p>${member.phone}</p>
                        <p>Rôle: ${getRoleLabel(member.role)}</p>
                        <span class="member-status ${member.status}">${member.status === 'active' ? 'Actif' : 'Suspendu'}</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Contributions récentes
    const transactions = TransactionService.findByCircleId(circleId).slice(0, 5);
    const contributionsContainer = document.getElementById('recentContributions');
    
    if (transactions.length === 0) {
        contributionsContainer.innerHTML = '<div class="empty-state"><p>Aucune contribution</p></div>';
    } else {
        contributionsContainer.innerHTML = transactions.map(t => {
            const member = members.find(m => m.id === t.memberId);
            return `
                <div class="transaction-item">
                    <div class="transaction-info">
                        <h4>${member ? `${member.firstName} ${member.lastName}` : 'Membre'}</h4>
                        <p>${getTransactionTypeLabel(t.type)} - ${formatDate(t.date)}</p>
                    </div>
                    <div class="transaction-amount ${t.type === 'epargne' || t.type === 'remboursement' ? 'positive' : 'negative'}">
                        ${t.type === 'epargne' || t.type === 'remboursement' ? '+' : '-'}${formatAmount(t.amount)}
                    </div>
                </div>
            `;
        }).join('');
    }

    showPage('circleDetails');
}

/**
 * Ouvrir le modal de membre
 */
function openMemberModal(memberId = null) {
    const modal = document.getElementById('memberModal');
    const form = document.getElementById('memberForm');
    const title = document.getElementById('memberModalTitle');
    
    if (memberId) {
        // Mode édition
        const members = MemberService.findByCircleId(currentCircleId);
        const member = members.find(m => m.id === memberId);
        if (member) {
            title.textContent = 'Modifier le membre';
            document.getElementById('memberFirstName').value = member.firstName;
            document.getElementById('memberLastName').value = member.lastName;
            document.getElementById('memberPhone').value = member.phone;
            document.getElementById('memberRole').value = member.role;
            form.dataset.memberId = memberId;
        }
    } else {
        // Mode création
        title.textContent = 'Ajouter un membre';
        form.reset();
        delete form.dataset.memberId;
    }
    
    modal.classList.add('active');
}

/**
 * Fermer le modal de membre
 */
function closeMemberModal() {
    document.getElementById('memberModal').classList.remove('active');
    document.getElementById('memberForm').reset();
}

/**
 * Gérer la soumission du formulaire de membre
 */
function handleMemberSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const memberId = form.dataset.memberId;

    const memberData = {
        firstName: document.getElementById('memberFirstName').value,
        lastName: document.getElementById('memberLastName').value,
        phone: document.getElementById('memberPhone').value,
        role: document.getElementById('memberRole').value
    };

    if (!currentCircleId) {
        alert('Erreur: Aucun cercle sélectionné');
        return;
    }

    try {
        if (memberId) {
            MemberService.update(currentCircleId, memberId, memberData);
            alert('Membre modifié avec succès');
        } else {
            MemberService.addToCircle(currentCircleId, memberData);
            alert('Membre ajouté avec succès');
        }
        closeMemberModal();
        viewCircleDetails(currentCircleId);
    } catch (error) {
        alert(error.message);
    }
}

/**
 * Configuration des événements de transaction
 */
function setupTransactionEvents() {
    document.getElementById('btnAddTransaction').addEventListener('click', () => {
        openTransactionModal();
    });

    document.getElementById('transactionForm').addEventListener('submit', handleTransactionSubmit);
    document.getElementById('closeTransactionModal').addEventListener('click', closeTransactionModal);
    document.getElementById('cancelTransactionForm').addEventListener('click', closeTransactionModal);

    // Filtres
    document.getElementById('filterCircle').addEventListener('change', loadTransactions);
    document.getElementById('filterType').addEventListener('change', loadTransactions);
    document.getElementById('filterDateFrom').addEventListener('change', loadTransactions);
    document.getElementById('filterDateTo').addEventListener('change', loadTransactions);
}

/**
 * Charger les transactions
 */
function loadTransactions() {
    const filters = {
        circleId: document.getElementById('filterCircle').value || null,
        type: document.getElementById('filterType').value || null,
        dateFrom: document.getElementById('filterDateFrom').value || null,
        dateTo: document.getElementById('filterDateTo').value || null
    };

    const transactions = TransactionService.filter(filters);
    const container = document.getElementById('transactionsList');
    
    // Remplir le filtre de cercle
    const circleFilter = document.getElementById('filterCircle');
    const userCircles = CircleService.findByUserId(currentUser.id);
    circleFilter.innerHTML = '<option value="">Tous les cercles</option>' +
        userCircles.map(c => `<option value="${c.id}">${c.name}</option>`).join('');

    if (transactions.length === 0) {
        container.innerHTML = '<div class="empty-state"><p>Aucune transaction</p></div>';
        return;
    }

    container.innerHTML = transactions.map(t => {
        const circle = CircleService.findById(t.circleId);
        const members = circle ? MemberService.findByCircleId(t.circleId) : [];
        const member = members.find(m => m.id === t.memberId);
        
        return `
            <div class="transaction-item">
                <div class="transaction-info">
                    <h4>${getTransactionTypeLabel(t.type)}</h4>
                    <p>${circle ? circle.name : 'Cercle'} - ${member ? `${member.firstName} ${member.lastName}` : 'Membre'}</p>
                    <p><small>${formatDate(t.date)} - ${getPaymentMethodLabel(t.paymentMethod)}</small></p>
                    ${t.notes ? `<p><small>${t.notes}</small></p>` : ''}
                </div>
                <div class="transaction-amount ${t.type === 'epargne' || t.type === 'remboursement' ? 'positive' : 'negative'}">
                    ${t.type === 'epargne' || t.type === 'remboursement' ? '+' : '-'}${formatAmount(t.amount)}
                </div>
            </div>
        `;
    }).join('');
}

/**
 * Ouvrir le modal de transaction
 */
function openTransactionModal() {
    const modal = document.getElementById('transactionModal');
    const circleSelect = document.getElementById('transactionCircle');
    const userCircles = CircleService.findByUserId(currentUser.id);
    
    circleSelect.innerHTML = userCircles.map(c => 
        `<option value="${c.id}">${c.name}</option>`
    ).join('');

    if (userCircles.length === 0) {
        alert('Vous devez créer un cercle avant d\'ajouter une transaction');
        return;
    }

    // Charger les membres quand un cercle est sélectionné
    circleSelect.addEventListener('change', (e) => {
        const circleId = e.target.value;
        const memberSelect = document.getElementById('transactionMember');
        const members = MemberService.findByCircleId(circleId);
        memberSelect.innerHTML = members.map(m => 
            `<option value="${m.id}">${m.firstName} ${m.lastName}</option>`
        ).join('');
    });

    // Déclencher le changement pour charger les membres du premier cercle
    if (userCircles.length > 0) {
        circleSelect.dispatchEvent(new Event('change'));
    }

    document.getElementById('transactionDate').value = new Date().toISOString().split('T')[0];
    modal.classList.add('active');
}

/**
 * Fermer le modal de transaction
 */
function closeTransactionModal() {
    document.getElementById('transactionModal').classList.remove('active');
    document.getElementById('transactionForm').reset();
}

/**
 * Gérer la soumission du formulaire de transaction
 */
function handleTransactionSubmit(e) {
    e.preventDefault();
    
    const transactionData = {
        circleId: document.getElementById('transactionCircle').value,
        memberId: document.getElementById('transactionMember').value,
        type: document.getElementById('transactionType').value,
        amount: parseFloat(document.getElementById('transactionAmount').value),
        date: document.getElementById('transactionDate').value,
        paymentMethod: document.getElementById('transactionPaymentMethod').value,
        notes: document.getElementById('transactionNotes').value
    };

    TransactionService.create(transactionData);
    alert('Transaction enregistrée avec succès');
    closeTransactionModal();
    loadTransactions();
    loadDashboard();
}

/**
 * Configuration des événements de prêt
 */
function setupLoanEvents() {
    document.getElementById('btnRequestLoan').addEventListener('click', () => {
        openLoanModal();
    });

    document.getElementById('loanForm').addEventListener('submit', handleLoanSubmit);
    document.getElementById('closeLoanModal').addEventListener('click', closeLoanModal);
    document.getElementById('cancelLoanForm').addEventListener('click', closeLoanModal);

    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            const tab = e.target.getAttribute('data-tab');
            loadLoans(tab);
        });
    });
}

/**
 * Charger les prêts
 */
function loadLoans(status = 'pending') {
    const userCircles = CircleService.findByUserId(currentUser.id);
    const circleIds = userCircles.map(c => c.id);
    const allLoans = [];
    
    circleIds.forEach(circleId => {
        const loans = LoanService.findByCircleId(circleId);
        allLoans.push(...loans);
    });

    let loans = allLoans;
    if (status !== 'all') {
        loans = LoanService.filterByStatus(status);
        loans = loans.filter(l => circleIds.includes(l.circleId));
    }

    const container = document.getElementById('loansList');
    
    if (loans.length === 0) {
        container.innerHTML = '<div class="empty-state"><p>Aucun prêt</p></div>';
        return;
    }

    container.innerHTML = loans.map(loan => {
        const circle = CircleService.findById(loan.circleId);
        const members = circle ? MemberService.findByCircleId(loan.circleId) : [];
        const member = members.find(m => m.id === loan.memberId);
        const progress = ((loan.amount - loan.remainingAmount) / loan.amount * 100).toFixed(0);

        return `
            <div class="loan-card">
                <div class="loan-header">
                    <div>
                        <h3>Prêt #${loan.id.slice(-6)}</h3>
                        <p>${circle ? circle.name : 'Cercle'} - ${member ? `${member.firstName} ${member.lastName}` : 'Membre'}</p>
                    </div>
                    <span class="loan-status ${loan.status}">${getLoanStatusLabel(loan.status)}</span>
                </div>
                <div class="loan-details">
                    <p><strong>Montant:</strong> ${formatAmount(loan.amount)}</p>
                    <p><strong>Reste à payer:</strong> ${formatAmount(loan.remainingAmount)}</p>
                    <p><strong>Progression:</strong> ${progress}%</p>
                    <p><strong>Motif:</strong> ${loan.purpose}</p>
                    <p><strong>Date:</strong> ${formatDate(loan.createdAt)}</p>
                </div>
                ${loan.status === 'pending' && currentUser.role === 'admin' ? `
                    <div style="margin-top: 1rem; display: flex; gap: 1rem;">
                        <button class="btn btn-primary" onclick="approveLoan('${loan.id}')">Approuver</button>
                        <button class="btn btn-secondary" onclick="rejectLoan('${loan.id}')">Rejeter</button>
                    </div>
                ` : ''}
            </div>
        `;
    }).join('');
}

/**
 * Ouvrir le modal de prêt
 */
function openLoanModal() {
    const modal = document.getElementById('loanModal');
    const circleSelect = document.getElementById('loanCircle');
    const userCircles = CircleService.findByUserId(currentUser.id);
    
    circleSelect.innerHTML = userCircles.map(c => 
        `<option value="${c.id}">${c.name}</option>`
    ).join('');

    if (userCircles.length === 0) {
        alert('Vous devez créer un cercle avant de demander un prêt');
        return;
    }

    modal.classList.add('active');
}

/**
 * Fermer le modal de prêt
 */
function closeLoanModal() {
    document.getElementById('loanModal').classList.remove('active');
    document.getElementById('loanForm').reset();
}

/**
 * Gérer la soumission du formulaire de prêt
 */
function handleLoanSubmit(e) {
    e.preventDefault();
    
    const circleId = document.getElementById('loanCircle').value;
    const members = MemberService.findByCircleId(circleId);
    const currentMember = members.find(m => m.userId === currentUser.id);
    
    if (!currentMember) {
        alert('Vous n\'êtes pas membre de ce cercle');
        return;
    }

    const loanData = {
        circleId: circleId,
        memberId: currentMember.id,
        amount: parseFloat(document.getElementById('loanAmount').value),
        purpose: document.getElementById('loanPurpose').value,
        duration: parseInt(document.getElementById('loanDuration').value)
    };

    LoanService.create(loanData);
    alert('Demande de prêt soumise avec succès');
    closeLoanModal();
    loadLoans();
}

/**
 * Approuver un prêt
 */
function approveLoan(loanId) {
    if (confirm('Approuver ce prêt ?')) {
        LoanService.approve(loanId);
        alert('Prêt approuvé');
        loadLoans();
        loadDashboard();
    }
}

/**
 * Rejeter un prêt
 */
function rejectLoan(loanId) {
    if (confirm('Rejeter ce prêt ?')) {
        LoanService.reject(loanId);
        alert('Prêt rejeté');
        loadLoans();
    }
}

/**
 * Configuration des événements de rapports
 */
function setupReportEvents() {
    document.getElementById('btnExportCSV').addEventListener('click', exportCSV);
    document.getElementById('btnExportPDF').addEventListener('click', exportPDF);
    
    document.getElementById('reportCircle').addEventListener('change', loadReports);
    document.getElementById('reportMember').addEventListener('change', loadReports);
    document.getElementById('reportPeriod').addEventListener('change', loadReports);
}

/**
 * Charger les rapports
 */
function loadReports() {
    const circleId = document.getElementById('reportCircle').value;
    const memberId = document.getElementById('reportMember').value;
    const period = document.getElementById('reportPeriod').value;

    // Remplir les sélecteurs
    const userCircles = CircleService.findByUserId(currentUser.id);
    const circleSelect = document.getElementById('reportCircle');
    circleSelect.innerHTML = '<option value="">Tous les cercles</option>' +
        userCircles.map(c => `<option value="${c.id}">${c.name}</option>`).join('');

    let transactions = TransactionService.findByUserId(currentUser.id);
    
    if (circleId) {
        transactions = transactions.filter(t => t.circleId === circleId);
    }

    // Filtrer par période
    const now = new Date();
    let dateFrom = null;
    
    switch(period) {
        case 'week':
            dateFrom = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
            break;
        case 'month':
            dateFrom = new Date(now.getFullYear(), now.getMonth(), 1);
            break;
        case 'quarter':
            dateFrom = new Date(now.getFullYear(), now.getMonth() - 2, 1);
            break;
        case 'year':
            dateFrom = new Date(now.getFullYear(), 0, 1);
            break;
    }

    if (dateFrom) {
        transactions = transactions.filter(t => new Date(t.date) >= dateFrom);
    }

    const container = document.getElementById('reportsContent');
    
    // Statistiques
    const totalContributions = transactions
        .filter(t => t.type === 'epargne')
        .reduce((sum, t) => sum + t.amount, 0);
    
    const totalLoans = transactions
        .filter(t => t.type === 'pret')
        .reduce((sum, t) => sum + t.amount, 0);

    container.innerHTML = `
        <div class="stats-grid" style="margin-bottom: 2rem;">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <div class="stat-content">
                    <h3>Total Contributions</h3>
                    <p class="stat-value">${formatAmount(totalContributions)}</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
                <div class="stat-content">
                    <h3>Total Prêts</h3>
                    <p class="stat-value">${formatAmount(totalLoans)}</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-list"></i></div>
                <div class="stat-content">
                    <h3>Nombre de Transactions</h3>
                    <p class="stat-value">${transactions.length}</p>
                </div>
            </div>
        </div>
        <div class="transactions-list">
            ${transactions.map(t => {
                const circle = CircleService.findById(t.circleId);
                const members = circle ? MemberService.findByCircleId(t.circleId) : [];
                const member = members.find(m => m.id === t.memberId);
                
                return `
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <h4>${getTransactionTypeLabel(t.type)}</h4>
                            <p>${circle ? circle.name : 'Cercle'} - ${member ? `${member.firstName} ${member.lastName}` : 'Membre'}</p>
                            <p><small>${formatDate(t.date)}</small></p>
                        </div>
                        <div class="transaction-amount ${t.type === 'epargne' || t.type === 'remboursement' ? 'positive' : 'negative'}">
                            ${t.type === 'epargne' || t.type === 'remboursement' ? '+' : '-'}${formatAmount(t.amount)}
                        </div>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

/**
 * Exporter en CSV
 */
function exportCSV() {
    const transactions = TransactionService.findByUserId(currentUser.id);
    const headers = ['Date', 'Type', 'Cercle', 'Membre', 'Montant', 'Moyen de paiement'];
    const rows = transactions.map(t => {
        const circle = CircleService.findById(t.circleId);
        const members = circle ? MemberService.findByCircleId(t.circleId) : [];
        const member = members.find(m => m.id === t.memberId);
        
        return [
            t.date,
            getTransactionTypeLabel(t.type),
            circle ? circle.name : '',
            member ? `${member.firstName} ${member.lastName}` : '',
            t.amount,
            getPaymentMethodLabel(t.paymentMethod)
        ];
    });

    const csv = [headers, ...rows].map(row => row.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `twungurane_rapport_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
    alert('Rapport CSV exporté avec succès');
}

/**
 * Exporter en PDF (simulation)
 */
function exportPDF() {
    alert('Export PDF en simulation. En production, cette fonctionnalité utiliserait une bibliothèque comme jsPDF.');
}

/**
 * Configuration des événements de contact
 */
function setupContactEvents() {
    document.getElementById('contactForm').addEventListener('submit', handleContactSubmit);
}

/**
 * Gérer la soumission du formulaire de contact
 */
function handleContactSubmit(e) {
    e.preventDefault();
    
    const formData = {
        name: document.getElementById('contactName').value,
        phone: document.getElementById('contactPhone').value,
        email: document.getElementById('contactEmail').value,
        message: document.getElementById('contactMessage').value
    };

    // Validation
    if (!validatePhone(formData.phone)) {
        alert('Numéro de téléphone invalide');
        return;
    }

    if (!validateEmail(formData.email)) {
        alert('Adresse email invalide');
        return;
    }

    // Simulation d'envoi
    alert('Message envoyé avec succès ! Notre équipe vous répondra dans les plus brefs délais.');
    document.getElementById('contactForm').reset();
}

/**
 * Configuration du menu mobile
 */
function setupMobileMenu() {
    const toggle = document.getElementById('navToggle');
    const menu = document.getElementById('navMenu');
    
    toggle.addEventListener('click', () => {
        menu.classList.toggle('active');
    });

    // Fermer le menu quand on clique sur un lien
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            menu.classList.remove('active');
        });
    });
}

/**
 * Configuration des liens du footer
 */
function setupFooterLinks() {
    document.querySelectorAll('.footer-link[data-page]').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const page = link.getAttribute('data-page');
            if (page && currentUser) {
                showPage(page);
                // Scroll vers le haut de la page
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    });
}

/**
 * Fonctions utilitaires
 */
function validatePhone(phone) {
    return /^\+257\s?\d{2}\s?\d{2}\s?\d{2}\s?\d{2}$/.test(phone);
}

function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function getRoleLabel(role) {
    const labels = {
        'admin': 'Administrateur',
        'treasurer': 'Trésorier',
        'member': 'Membre'
    };
    return labels[role] || role;
}

function getTransactionTypeLabel(type) {
    const labels = {
        'epargne': 'Épargne',
        'penalite': 'Pénalité',
        'pret': 'Prêt',
        'remboursement': 'Remboursement'
    };
    return labels[type] || type;
}

function getPaymentMethodLabel(method) {
    const labels = {
        'lumicash': 'Lumicash',
        'ecocash': 'EcoCash',
        'mpesa': 'M-Pesa',
        'especes': 'Espèces',
        'internal': 'Interne'
    };
    return labels[method] || method;
}

function getLoanStatusLabel(status) {
    const labels = {
        'pending': 'En attente',
        'active': 'Actif',
        'completed': 'Terminé',
        'rejected': 'Rejeté'
    };
    return labels[status] || status;
}

// Exposer les fonctions globales nécessaires
window.viewCircleDetails = viewCircleDetails;
window.approveLoan = approveLoan;
window.rejectLoan = rejectLoan;

