"""
TWUNGURANE - Router Analytics
Endpoints généraux d'analyse financière
"""

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field
from typing import List, Optional, Dict, Any
from datetime import date
from app.services.analytics_service import AnalyticsService

router = APIRouter()
analytics_service = AnalyticsService()


# =============================================================================
# MODÈLES PYDANTIC
# =============================================================================

class ContributionData(BaseModel):
    """Données d'une contribution"""
    user_id: int
    montant: float
    type: str
    date: str


class LoanData(BaseModel):
    """Données d'un prêt"""
    user_id: int
    montant: float
    montant_restant: float
    statut: str
    date_creation: str


class MemberData(BaseModel):
    """Données d'un membre"""
    id: int
    user_id: int
    role: str
    date_adhesion: str
    statut: str


class GroupStatistics(BaseModel):
    """Statistiques d'un groupe"""
    total_contributions: float = 0
    total_prets: float = 0
    membres_actifs: int = 0


class GroupDataRequest(BaseModel):
    """Requête avec les données d'un groupe"""
    group_id: int
    type: str
    montant_contribution: float
    frequence: str
    duree_cycle: int
    date_debut: Optional[str] = None
    balance: float = 0
    membres: List[MemberData] = []
    contributions: List[ContributionData] = []
    prets: List[LoanData] = []
    statistiques: GroupStatistics = GroupStatistics()


class DashboardRequest(BaseModel):
    """Requête pour le dashboard analytique"""
    groups: List[GroupDataRequest]
    periode_mois: int = Field(default=12, ge=1, le=36)


class DashboardResponse(BaseModel):
    """Réponse du dashboard analytique"""
    resume: Dict[str, Any]
    tendances: Dict[str, Any]
    alertes: List[Dict[str, Any]]
    recommandations: List[str]


# =============================================================================
# ENDPOINTS
# =============================================================================

@router.get("/dashboard-summary")
async def get_dashboard_summary():
    """
    Obtenir un résumé du dashboard analytique
    """
    return {
        "status": "operational",
        "available_endpoints": [
            "POST /analytics/dashboard",
            "POST /analytics/trends",
            "POST /analytics/alerts",
        ],
        "description": "Service d'analyse des données VSLA"
    }


@router.post("/dashboard", response_model=DashboardResponse)
async def analyze_dashboard(request: DashboardRequest):
    """
    Analyser les données pour le dashboard principal
    
    Fournit:
    - Résumé global des performances
    - Tendances mensuelles
    - Alertes automatiques
    - Recommandations
    """
    try:
        result = analytics_service.analyze_dashboard(
            groups=[g.dict() for g in request.groups],
            periode_mois=request.periode_mois
        )
        return result
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/trends")
async def analyze_trends(request: GroupDataRequest):
    """
    Analyser les tendances d'un groupe
    
    - Évolution des contributions
    - Tendance des prêts
    - Prévisions
    """
    try:
        result = analytics_service.analyze_trends(request.dict())
        return {
            "group_id": request.group_id,
            "tendances": result
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/alerts")
async def generate_alerts(request: GroupDataRequest):
    """
    Générer les alertes pour un groupe
    
    Types d'alertes:
    - Membres inactifs
    - Prêts en retard
    - Taux de participation faible
    - Risques financiers
    """
    try:
        alerts = analytics_service.generate_alerts(request.dict())
        return {
            "group_id": request.group_id,
            "alertes": alerts,
            "count": len(alerts)
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/compare-groups")
async def compare_groups(groups: List[GroupDataRequest]):
    """
    Comparer les performances de plusieurs groupes
    """
    if len(groups) < 2:
        raise HTTPException(status_code=400, detail="Au moins 2 groupes requis pour la comparaison")
    
    try:
        result = analytics_service.compare_groups([g.dict() for g in groups])
        return {
            "comparaison": result,
            "groups_count": len(groups)
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
