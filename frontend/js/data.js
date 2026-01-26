/**
 * TWUNGURANE - Gestion des données
 * Simulation de base de données avec LocalStorage
 */

// Structure de données par défaut
const defaultData = {
    users: [],
    circles: [],
    members: [],
    transactions: [],
    loans: [],
    notifications: []
};

/**
 * Initialisation des données
 */
function initData() {
    if (!localStorage.getItem('twungurane_data')) {
        localStorage.setItem('twungurane_data', JSON.stringify(defaultData));
    }
}

/**
 * Récupération des données
 */
function getData() {
    const data = localStorage.getItem('twungurane_data');
    return data ? JSON.parse(data) : defaultData;
}

/**
 * Sauvegarde des données
 */
function saveData(data) {
    localStorage.setItem('twungurane_data', JSON.stringify(data));
}

/**
 * Gestion des utilisateurs
 */
const UserService = {
    // Créer un utilisateur
    create(user) {
        const data = getData();
        const newUser = {
            id: Date.now().toString(),
            ...user,
            createdAt: new Date().toISOString()
        };
        data.users.push(newUser);
        saveData(data);
        return newUser;
    },

    // Trouver un utilisateur par identifiant (téléphone ou email)
    findByIdentifier(identifier) {
        const data = getData();
        return data.users.find(u => 
            u.phone === identifier || u.email === identifier
        );
    },

    // Trouver un utilisateur par ID
    findById(id) {
        const data = getData();
        return data.users.find(u => u.id === id);
    },

    // Mettre à jour un utilisateur
    update(id, updates) {
        const data = getData();
        const index = data.users.findIndex(u => u.id === id);
        if (index !== -1) {
            data.users[index] = { ...data.users[index], ...updates };
            saveData(data);
            return data.users[index];
        }
        return null;
    }
};

/**
 * Gestion des cercles d'épargne
 */
const CircleService = {
    // Créer un cercle
    create(circle) {
        const data = getData();
        const newCircle = {
            id: Date.now().toString(),
            ...circle,
            createdAt: new Date().toISOString(),
            totalBalance: 0
        };
        data.circles.push(newCircle);
        saveData(data);
        return newCircle;
    },

    // Récupérer tous les cercles d'un utilisateur
    findByUserId(userId) {
        const data = getData();
        return data.circles.filter(c => 
            c.members.some(m => m.userId === userId)
        );
    },

    // Trouver un cercle par ID
    findById(id) {
        const data = getData();
        return data.circles.find(c => c.id === id);
    },

    // Mettre à jour un cercle
    update(id, updates) {
        const data = getData();
        const index = data.circles.findIndex(c => c.id === id);
        if (index !== -1) {
            data.circles[index] = { ...data.circles[index], ...updates };
            saveData(data);
            return data.circles[index];
        }
        return null;
    },

    // Supprimer un cercle
    delete(id) {
        const data = getData();
        data.circles = data.circles.filter(c => c.id !== id);
        saveData(data);
    },

    // Calculer le solde total d'un cercle
    calculateBalance(circleId) {
        const data = getData();
        const transactions = data.transactions.filter(t => t.circleId === circleId);
        let balance = 0;
        transactions.forEach(t => {
            if (t.type === 'epargne' || t.type === 'penalite') {
                balance += t.amount;
            } else if (t.type === 'pret') {
                balance -= t.amount;
            } else if (t.type === 'remboursement') {
                balance += t.amount;
            }
        });
        return balance;
    }
};

/**
 * Gestion des membres
 */
const MemberService = {
    // Ajouter un membre à un cercle
    addToCircle(circleId, member) {
        const data = getData();
        const circle = data.circles.find(c => c.id === circleId);
        if (!circle) return null;

        // Vérifier la limite de 20 membres
        if (circle.members && circle.members.length >= circle.maxMembers) {
            throw new Error('Le cercle a atteint le nombre maximum de membres (20)');
        }

        const newMember = {
            id: Date.now().toString(),
            ...member,
            circleId,
            joinedAt: new Date().toISOString(),
            status: 'active',
            totalContributions: 0
        };

        if (!circle.members) {
            circle.members = [];
        }
        circle.members.push(newMember);
        saveData(data);
        return newMember;
    },

    // Mettre à jour un membre
    update(circleId, memberId, updates) {
        const data = getData();
        const circle = data.circles.find(c => c.id === circleId);
        if (!circle || !circle.members) return null;

        const index = circle.members.findIndex(m => m.id === memberId);
        if (index !== -1) {
            circle.members[index] = { ...circle.members[index], ...updates };
            saveData(data);
            return circle.members[index];
        }
        return null;
    },

    // Supprimer un membre
    remove(circleId, memberId) {
        const data = getData();
        const circle = data.circles.find(c => c.id === circleId);
        if (!circle || !circle.members) return false;

        circle.members = circle.members.filter(m => m.id !== memberId);
        saveData(data);
        return true;
    },

    // Récupérer les membres d'un cercle
    findByCircleId(circleId) {
        const data = getData();
        const circle = data.circles.find(c => c.id === circleId);
        return circle ? (circle.members || []) : [];
    }
};

/**
 * Gestion des transactions
 */
const TransactionService = {
    // Créer une transaction
    create(transaction) {
        const data = getData();
        const newTransaction = {
            id: Date.now().toString(),
            ...transaction,
            createdAt: new Date().toISOString()
        };
        data.transactions.push(newTransaction);
        
        // Mettre à jour le solde du cercle
        const circle = data.circles.find(c => c.id === transaction.circleId);
        if (circle) {
            if (transaction.type === 'epargne' || transaction.type === 'penalite') {
                circle.totalBalance = (circle.totalBalance || 0) + transaction.amount;
            } else if (transaction.type === 'pret') {
                circle.totalBalance = (circle.totalBalance || 0) - transaction.amount;
            } else if (transaction.type === 'remboursement') {
                circle.totalBalance = (circle.totalBalance || 0) + transaction.amount;
            }
        }

        // Mettre à jour les contributions du membre
        if (transaction.memberId) {
            const circle = data.circles.find(c => c.id === transaction.circleId);
            if (circle && circle.members) {
                const member = circle.members.find(m => m.id === transaction.memberId);
                if (member && transaction.type === 'epargne') {
                    member.totalContributions = (member.totalContributions || 0) + transaction.amount;
                }
            }
        }

        saveData(data);
        return newTransaction;
    },

    // Récupérer les transactions d'un cercle
    findByCircleId(circleId) {
        const data = getData();
        return data.transactions.filter(t => t.circleId === circleId)
            .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
    },

    // Récupérer les transactions d'un utilisateur
    findByUserId(userId) {
        const data = getData();
        // Trouver tous les cercles où l'utilisateur est membre
        const userCircles = data.circles.filter(c => 
            c.members && c.members.some(m => m.userId === userId)
        );
        const circleIds = userCircles.map(c => c.id);
        return data.transactions.filter(t => circleIds.includes(t.circleId))
            .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
    },

    // Filtrer les transactions
    filter(filters) {
        const data = getData();
        let transactions = [...data.transactions];

        if (filters.circleId) {
            transactions = transactions.filter(t => t.circleId === filters.circleId);
        }

        if (filters.type) {
            transactions = transactions.filter(t => t.type === filters.type);
        }

        if (filters.dateFrom) {
            transactions = transactions.filter(t => 
                new Date(t.date) >= new Date(filters.dateFrom)
            );
        }

        if (filters.dateTo) {
            transactions = transactions.filter(t => 
                new Date(t.date) <= new Date(filters.dateTo)
            );
        }

        return transactions.sort((a, b) => new Date(b.date) - new Date(a.date));
    }
};

/**
 * Gestion des prêts
 */
const LoanService = {
    // Créer une demande de prêt
    create(loan) {
        const data = getData();
        const newLoan = {
            id: Date.now().toString(),
            ...loan,
            status: 'pending',
            createdAt: new Date().toISOString(),
            remainingAmount: loan.amount,
            payments: []
        };
        data.loans.push(newLoan);
        saveData(data);
        return newLoan;
    },

    // Approuver un prêt
    approve(loanId) {
        const data = getData();
        const loan = data.loans.find(l => l.id === loanId);
        if (loan) {
            loan.status = 'active';
            loan.approvedAt = new Date().toISOString();
            
            // Créer une transaction de prêt
            TransactionService.create({
                circleId: loan.circleId,
                memberId: loan.memberId,
                type: 'pret',
                amount: loan.amount,
                date: new Date().toISOString().split('T')[0],
                paymentMethod: 'internal',
                notes: `Prêt approuvé: ${loan.purpose}`
            });

            saveData(data);
            return loan;
        }
        return null;
    },

    // Rejeter un prêt
    reject(loanId) {
        const data = getData();
        const loan = data.loans.find(l => l.id === loanId);
        if (loan) {
            loan.status = 'rejected';
            saveData(data);
            return loan;
        }
        return null;
    },

    // Enregistrer un remboursement
    addPayment(loanId, payment) {
        const data = getData();
        const loan = data.loans.find(l => l.id === loanId);
        if (loan) {
            const paymentRecord = {
                id: Date.now().toString(),
                ...payment,
                date: new Date().toISOString().split('T')[0],
                createdAt: new Date().toISOString()
            };
            loan.payments.push(paymentRecord);
            loan.remainingAmount = loan.remainingAmount - payment.amount;

            // Si le prêt est complètement remboursé
            if (loan.remainingAmount <= 0) {
                loan.status = 'completed';
                loan.completedAt = new Date().toISOString();
            }

            // Créer une transaction de remboursement
            TransactionService.create({
                circleId: loan.circleId,
                memberId: loan.memberId,
                type: 'remboursement',
                amount: payment.amount,
                date: paymentRecord.date,
                paymentMethod: payment.paymentMethod || 'internal',
                notes: `Remboursement prêt #${loanId}`
            });

            saveData(data);
            return loan;
        }
        return null;
    },

    // Récupérer les prêts d'un cercle
    findByCircleId(circleId) {
        const data = getData();
        return data.loans.filter(l => l.circleId === circleId)
            .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
    },

    // Récupérer les prêts d'un membre
    findByMemberId(memberId) {
        const data = getData();
        return data.loans.filter(l => l.memberId === memberId)
            .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
    },

    // Filtrer les prêts par statut
    filterByStatus(status) {
        const data = getData();
        return data.loans.filter(l => l.status === status)
            .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
    }
};

/**
 * Gestion des notifications
 */
const NotificationService = {
    // Créer une notification
    create(notification) {
        const data = getData();
        const newNotification = {
            id: Date.now().toString(),
            ...notification,
            read: false,
            createdAt: new Date().toISOString()
        };
        data.notifications.push(newNotification);
        saveData(data);
        return newNotification;
    },

    // Récupérer les notifications d'un utilisateur
    findByUserId(userId) {
        const data = getData();
        return data.notifications.filter(n => n.userId === userId)
            .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
    },

    // Marquer une notification comme lue
    markAsRead(notificationId) {
        const data = getData();
        const notification = data.notifications.find(n => n.id === notificationId);
        if (notification) {
            notification.read = true;
            saveData(data);
            return notification;
        }
        return null;
    }
};

/**
 * Simulation OTP
 */
function generateOTP() {
    return Math.floor(100000 + Math.random() * 900000).toString();
}

/**
 * Formatage des montants
 */
function formatAmount(amount) {
    return new Intl.NumberFormat('fr-FR').format(amount) + ' FBU';
}

/**
 * Formatage des dates
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Initialiser les données au chargement
initData();

