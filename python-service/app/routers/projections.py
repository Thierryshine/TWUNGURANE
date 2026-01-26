"""
TWUNGURANE - Router Financial Projections
Endpoints pour les projections et simulations financières
"""

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field
from typing import List, Optional, Dict, Any
from datetime import date
from app.services.projection_service import ProjectionService

router = APIRouter()
projection_service = ProjectionService()


# =============================================================================
# MODÈLES PYDANTIC
# =============================================================================

class ProjectionRequest(BaseModel):
    """Requête pour une projection financière"""
    group_id: int
    balance_actuel: float = Field(ge=0)
    montant_contribution: float = Field(gt=0)
    frequence: str = Field(description="hebdomadaire ou mensuelle")
    membres_actifs: int = Field(gt=0)
    taux_participation: float = Field(ge=0, le=100, default=100)
    duree_mois: int = Field(ge=1, le=36, default=12)
    taux_prets: float = Field(ge=0, le=100, default=30, description="% du solde utilisé pour les prêts")
    taux_interet_prets: float = Field(ge=0, le=50, default=10)


class ProjectionResponse(BaseModel):
    """Réponse d'une projection financière"""
    group_id: int
    projection_mois: int
    solde_projete: float
    total_contributions: float
    total_interets: float
    evolution_mensuelle: List[Dict[str, Any]]
    hypotheses: Dict[str, Any]


class CycleSimulationRequest(BaseModel):
    """Requête pour simulation de cycle complet"""
    montant_contribution: float = Field(gt=0)
    frequence: str
    duree_cycle_mois: int = Field(ge=1, le=24)
    nombre_membres: int = Field(ge=2, le=50)
    taux_participation: float = Field(ge=50, le=100, default=95)
    taux_prets: float = Field(ge=0, le=80, default=40)
    taux_interet_prets: float = Field(ge=0, le=30, default=10)
    taux_penalites: float = Field(ge=0, le=20, default=5)


class CycleSimulationResponse(BaseModel):
    """Réponse de simulation de cycle"""
    parametres: Dict[str, Any]
    resultats: Dict[str, Any]
    evolution: List[Dict[str, Any]]
    distribution_finale: Dict[str, Any]
    indicateurs: Dict[str, Any]


class SavingsGoalRequest(BaseModel):
    """Requête pour calcul d'objectif d'épargne"""
    objectif_montant: float = Field(gt=0)
    montant_contribution_actuel: float = Field(gt=0)
    frequence: str
    membres_actifs: int = Field(gt=0)
    taux_participation: float = Field(ge=0, le=100, default=100)


# =============================================================================
# ENDPOINTS
# =============================================================================

@router.post("/financial-projection", response_model=ProjectionResponse)
async def create_financial_projection(request: ProjectionRequest):
    """
    Créer une projection financière pour un groupe
    
    Projette l'évolution du solde du groupe sur la période spécifiée
    en tenant compte des contributions, prêts et intérêts.
    """
    try:
        result = projection_service.create_projection(request.dict())
        return ProjectionResponse(
            group_id=request.group_id,
            projection_mois=request.duree_mois,
            **result
        )
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/cycle-simulation", response_model=CycleSimulationResponse)
async def simulate_cycle(request: CycleSimulationRequest):
    """
    Simuler un cycle complet d'épargne
    
    Simule:
    - Collecte des contributions
    - Distribution des prêts
    - Collecte des intérêts
    - Distribution finale aux membres
    """
    try:
        result = projection_service.simulate_cycle(request.dict())
        return CycleSimulationResponse(
            parametres=request.dict(),
            **result
        )
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/savings-goal")
async def calculate_savings_goal(request: SavingsGoalRequest):
    """
    Calculer le temps nécessaire pour atteindre un objectif d'épargne
    """
    try:
        result = projection_service.calculate_savings_goal(request.dict())
        return {
            "objectif": request.objectif_montant,
            **result
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/loan-capacity")
async def calculate_loan_capacity(request: ProjectionRequest):
    """
    Calculer la capacité de prêt du groupe
    """
    try:
        result = projection_service.calculate_loan_capacity(request.dict())
        return {
            "group_id": request.group_id,
            **result
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/interest-projection")
async def project_interest_earnings(request: ProjectionRequest):
    """
    Projeter les revenus d'intérêts sur les prêts
    """
    try:
        result = projection_service.project_interest_earnings(request.dict())
        return {
            "group_id": request.group_id,
            "periode_mois": request.duree_mois,
            **result
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/scenario-analysis")
async def analyze_scenarios(request: ProjectionRequest):
    """
    Analyser différents scénarios (optimiste, réaliste, pessimiste)
    """
    try:
        scenarios = projection_service.analyze_scenarios(request.dict())
        return {
            "group_id": request.group_id,
            "scenarios": scenarios
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
