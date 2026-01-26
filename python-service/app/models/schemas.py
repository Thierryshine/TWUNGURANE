"""
TWUNGURANE - Schémas Pydantic
Modèles de validation pour les requêtes et réponses API
"""

from pydantic import BaseModel, Field, validator
from typing import Optional, List, Dict, Any
from datetime import datetime, date
from enum import Enum


# ============================================================================
# ENUMS
# ============================================================================

class TypeContribution(str, Enum):
    EPARGNE = "epargne"
    PENALITE = "penalite"
    REMBOURSEMENT = "remboursement"
    INTERET = "interet"


class StatutPret(str, Enum):
    EN_ATTENTE = "en_attente"
    APPROUVE = "approuve"
    EN_COURS = "en_cours"
    REMBOURSE = "rembourse"
    REJETE = "rejete"
    DEFAUT = "defaut"


class FrequenceContribution(str, Enum):
    HEBDOMADAIRE = "hebdomadaire"
    BIMENSUELLE = "bimensuelle"
    MENSUELLE = "mensuelle"


# ============================================================================
# SCHEMAS DE MEMBRE
# ============================================================================

class MemberContributionHistory(BaseModel):
    """Historique des contributions d'un membre"""
    user_id: int
    total_contributions: float
    nombre_contributions: int
    contributions_a_temps: int
    contributions_en_retard: int
    taux_ponctualite: float
    derniere_contribution: Optional[datetime] = None


class MemberLoanHistory(BaseModel):
    """Historique des prêts d'un membre"""
    user_id: int
    prets_total: int
    prets_rembourses: int
    prets_en_cours: int
    prets_en_defaut: int
    montant_total_emprunte: float
    montant_total_rembourse: float
    taux_remboursement: float


class MemberData(BaseModel):
    """Données complètes d'un membre pour l'analyse"""
    user_id: int
    nom: str
    prenom: str
    date_adhesion: datetime
    contributions: MemberContributionHistory
    loans: MemberLoanHistory
    anciennete_mois: int = 0


# ============================================================================
# SCHEMAS DE GROUPE
# ============================================================================

class GroupData(BaseModel):
    """Données d'un groupe pour l'analyse"""
    group_id: int
    nom: str
    type: str
    montant_contribution: float
    frequence: FrequenceContribution
    duree_cycle: int
    membres_actifs: int
    date_debut_cycle: date
    solde_total: float = 0
    total_contributions: float = 0
    prets_actifs: float = 0


class GroupContributionStats(BaseModel):
    """Statistiques de contributions du groupe"""
    total_contributions: float
    moyenne_mensuelle: float
    taux_collecte: float  # Pourcentage des contributions attendues collectées
    contributions_par_membre: float
    mois_analyse: int = 6


# ============================================================================
# SCHEMAS DE REQUÊTE - RISK SCORE
# ============================================================================

class RiskScoreRequest(BaseModel):
    """Requête pour calculer le score de risque d'un membre"""
    user_id: int
    group_id: int
    montant_pret_demande: Optional[float] = None
    
    # Données du membre
    total_contributions: float = 0
    nombre_contributions: int = 0
    contributions_a_temps: int = 0
    contributions_en_retard: int = 0
    anciennete_mois: int = 0
    
    # Historique de prêts
    prets_anterieurs: int = 0
    prets_rembourses: int = 0
    prets_en_defaut: int = 0
    montant_total_rembourse: float = 0
    
    class Config:
        json_schema_extra = {
            "example": {
                "user_id": 1,
                "group_id": 1,
                "montant_pret_demande": 50000,
                "total_contributions": 200000,
                "nombre_contributions": 24,
                "contributions_a_temps": 22,
                "contributions_en_retard": 2,
                "anciennete_mois": 12,
                "prets_anterieurs": 2,
                "prets_rembourses": 2,
                "prets_en_defaut": 0,
                "montant_total_rembourse": 100000
            }
        }


class RiskScoreResponse(BaseModel):
    """Réponse du calcul de score de risque"""
    user_id: int
    group_id: int
    risk_score: float = Field(..., ge=0, le=100, description="Score de risque (0=faible, 100=élevé)")
    risk_level: str = Field(..., description="Niveau de risque: faible, modere, eleve, tres_eleve")
    probabilite_defaut: float = Field(..., ge=0, le=1, description="Probabilité de défaut (0-1)")
    montant_recommande: Optional[float] = None
    facteurs_risque: List[str] = []
    recommandations: List[str] = []
    eligible_pret: bool = True
    details: Dict[str, Any] = {}


# ============================================================================
# SCHEMAS DE REQUÊTE - PROJECTIONS FINANCIÈRES
# ============================================================================

class ProjectionRequest(BaseModel):
    """Requête pour une projection financière"""
    group_id: int
    periode_mois: int = Field(default=12, ge=1, le=36)
    
    # État actuel du groupe
    solde_actuel: float = 0
    contribution_mensuelle_moyenne: float = 0
    taux_collecte: float = Field(default=0.9, ge=0, le=1)
    membres_actifs: int = 0
    montant_contribution: float = 0
    
    # Paramètres de simulation
    taux_croissance_membres: float = Field(default=0.02, ge=0, le=0.5)
    taux_prets_moyen: float = Field(default=0.5, ge=0, le=1)  # % du fonds prêté
    taux_interet_moyen: float = Field(default=0.10, ge=0, le=0.5)
    
    class Config:
        json_schema_extra = {
            "example": {
                "group_id": 1,
                "periode_mois": 12,
                "solde_actuel": 500000,
                "contribution_mensuelle_moyenne": 50000,
                "taux_collecte": 0.92,
                "membres_actifs": 15,
                "montant_contribution": 5000,
                "taux_croissance_membres": 0.02,
                "taux_prets_moyen": 0.6,
                "taux_interet_moyen": 0.10
            }
        }


class ProjectionMensuelle(BaseModel):
    """Projection pour un mois"""
    mois: int
    date: str
    epargne_cumulee: float
    prets_estimes: float
    interets_estimes: float
    solde_projete: float
    membres_projetes: int


class ProjectionResponse(BaseModel):
    """Réponse de projection financière"""
    group_id: int
    periode_mois: int
    projections: List[ProjectionMensuelle]
    resume: Dict[str, Any]
    graphique_data: Dict[str, List[float]]


# ============================================================================
# SCHEMAS DE REQUÊTE - SANTÉ DU GROUPE
# ============================================================================

class GroupHealthRequest(BaseModel):
    """Requête pour analyser la santé d'un groupe"""
    group_id: int
    
    # Métriques du groupe
    membres_actifs: int
    membres_inactifs: int = 0
    solde_total: float = 0
    total_contributions: float = 0
    total_prets_en_cours: float = 0
    prets_en_retard: int = 0
    prets_total: int = 0
    
    # Taux de collecte
    contributions_attendues: float = 0
    contributions_recues: float = 0
    
    # Ancienneté
    date_creation: date
    duree_cycle_mois: int = 12
    
    class Config:
        json_schema_extra = {
            "example": {
                "group_id": 1,
                "membres_actifs": 18,
                "membres_inactifs": 2,
                "solde_total": 1500000,
                "total_contributions": 2000000,
                "total_prets_en_cours": 500000,
                "prets_en_retard": 1,
                "prets_total": 10,
                "contributions_attendues": 2200000,
                "contributions_recues": 2000000,
                "date_creation": "2023-01-15",
                "duree_cycle_mois": 12
            }
        }


class GroupHealthResponse(BaseModel):
    """Réponse d'analyse de santé du groupe"""
    group_id: int
    health_score: float = Field(..., ge=0, le=100)
    health_level: str
    indicateurs: Dict[str, float]
    points_forts: List[str]
    points_faibles: List[str]
    recommandations: List[str]
    tendance: str  # amelioration, stable, degradation


# ============================================================================
# SCHEMAS - CLASSEMENT DES MEMBRES
# ============================================================================

class MemberRankingRequest(BaseModel):
    """Requête pour le classement des membres"""
    group_id: int
    membres: List[MemberContributionHistory]


class MemberRank(BaseModel):
    """Rang d'un membre"""
    rang: int
    user_id: int
    nom: Optional[str] = None
    score_discipline: float
    taux_ponctualite: float
    regularite: float
    badge: str  # "or", "argent", "bronze", "membre"


class MemberRankingResponse(BaseModel):
    """Réponse du classement des membres"""
    group_id: int
    classement: List[MemberRank]
    statistiques: Dict[str, Any]


# ============================================================================
# SCHEMAS - SIMULATION DE CYCLE
# ============================================================================

class CycleSimulationRequest(BaseModel):
    """Requête pour simuler un cycle d'épargne"""
    nombre_membres: int = Field(..., ge=2, le=50)
    montant_contribution: float = Field(..., ge=1000)
    frequence: FrequenceContribution
    duree_mois: int = Field(..., ge=1, le=24)
    taux_interet_pret: float = Field(default=10, ge=0, le=50)
    taux_prets: float = Field(default=0.6, ge=0, le=1)
    taux_defaut: float = Field(default=0.05, ge=0, le=0.3)


class CycleSimulationResponse(BaseModel):
    """Réponse de simulation de cycle"""
    parametres: Dict[str, Any]
    resultats: Dict[str, float]
    projections_mensuelles: List[Dict[str, Any]]
    scenarios: Dict[str, Dict[str, float]]  # optimiste, realiste, pessimiste
