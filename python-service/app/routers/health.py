"""
TWUNGURANE - Router Group Health
Endpoints pour l'évaluation de la santé des groupes
"""

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field
from typing import List, Optional, Dict, Any
from app.services.health_service import HealthService

router = APIRouter()
health_service = HealthService()


# =============================================================================
# MODÈLES PYDANTIC
# =============================================================================

class MemberInfo(BaseModel):
    """Informations sur un membre"""
    id: int
    user_id: int
    role: str
    date_adhesion: str
    statut: str


class ContributionInfo(BaseModel):
    """Informations sur une contribution"""
    user_id: int
    montant: float
    type: str
    date: str


class LoanInfo(BaseModel):
    """Informations sur un prêt"""
    user_id: int
    montant: float
    montant_restant: float
    statut: str
    date_creation: str


class GroupStats(BaseModel):
    """Statistiques du groupe"""
    total_contributions: float = 0
    total_prets: float = 0
    membres_actifs: int = 0


class GroupHealthRequest(BaseModel):
    """Requête pour l'analyse de santé d'un groupe"""
    group_id: int
    type: str
    montant_contribution: float
    frequence: str
    duree_cycle: int
    date_debut: Optional[str] = None
    balance: float = 0
    membres: List[MemberInfo] = []
    contributions: List[ContributionInfo] = []
    prets: List[LoanInfo] = []
    statistiques: GroupStats = GroupStats()


class HealthScoreResponse(BaseModel):
    """Réponse du score de santé"""
    group_id: int
    score: float = Field(ge=0, le=100)
    niveau: str
    indicateurs: Dict[str, float]
    problemes: List[str]
    recommandations: List[str]
    tendance: str


class MemberRankingRequest(BaseModel):
    """Requête pour le classement des membres"""
    group_id: int
    montant_contribution: float
    frequence: str
    membres: List[Dict[str, Any]]


class RankedMember(BaseModel):
    """Membre classé"""
    user_id: int
    rang: int
    score: float
    contributions_total: float
    regularite: float
    prets_rembourses: int
    niveau: str


# =============================================================================
# ENDPOINTS
# =============================================================================

@router.post("/group-health", response_model=HealthScoreResponse)
async def calculate_group_health(request: GroupHealthRequest):
    """
    Calculer le score de santé d'un groupe
    
    Indicateurs analysés:
    - Taux de participation aux cotisations
    - Taux de remboursement des prêts
    - Diversification des contributions
    - Ratio prêts/épargne
    - Croissance du solde
    
    Niveaux:
    - 80-100: Excellent (vert)
    - 60-79: Bon (bleu)
    - 40-59: Moyen (jaune)
    - 20-39: Faible (orange)
    - 0-19: Critique (rouge)
    """
    try:
        result = health_service.calculate_health_score(request.dict())
        return HealthScoreResponse(
            group_id=request.group_id,
            **result
        )
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/member-ranking/{group_id}")
async def get_member_ranking(group_id: int, members: Optional[str] = None):
    """
    Obtenir le classement des membres d'un groupe
    
    Le classement est basé sur:
    - Régularité des contributions (40%)
    - Montant total contribué (30%)
    - Historique de remboursement (20%)
    - Ancienneté (10%)
    """
    try:
        # Note: En production, les données seraient passées dans le body
        # Ici on retourne un exemple
        return {
            "group_id": group_id,
            "message": "Utilisez POST /member-ranking avec les données des membres",
            "endpoint": "POST /api/v1/member-ranking"
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/member-ranking")
async def calculate_member_ranking(request: MemberRankingRequest):
    """
    Calculer le classement des membres d'un groupe
    """
    try:
        rankings = health_service.calculate_member_ranking(request.dict())
        return {
            "group_id": request.group_id,
            "classement": rankings,
            "total_membres": len(rankings)
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/participation-analysis")
async def analyze_participation(request: GroupHealthRequest):
    """
    Analyser en détail la participation des membres
    """
    try:
        result = health_service.analyze_participation(request.dict())
        return {
            "group_id": request.group_id,
            "analyse": result
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/loan-performance")
async def analyze_loan_performance(request: GroupHealthRequest):
    """
    Analyser la performance des prêts du groupe
    """
    try:
        result = health_service.analyze_loan_performance(request.dict())
        return {
            "group_id": request.group_id,
            "performance_prets": result
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/growth-analysis")
async def analyze_growth(request: GroupHealthRequest):
    """
    Analyser la croissance du groupe
    """
    try:
        result = health_service.analyze_growth(request.dict())
        return {
            "group_id": request.group_id,
            "croissance": result
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/benchmark")
async def benchmark_group(request: GroupHealthRequest):
    """
    Comparer le groupe aux standards du secteur VSLA
    """
    try:
        result = health_service.benchmark_group(request.dict())
        return {
            "group_id": request.group_id,
            "benchmark": result
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
