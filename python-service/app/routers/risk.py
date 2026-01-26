"""
TWUNGURANE - Router Risk Assessment
Endpoints pour l'évaluation des risques de défaut
"""

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field
from typing import List, Optional, Dict, Any
from datetime import date
from app.services.risk_service import RiskService

router = APIRouter()
risk_service = RiskService()


# =============================================================================
# MODÈLES PYDANTIC
# =============================================================================

class ContributionHistory(BaseModel):
    """Historique d'une contribution"""
    montant: float
    type: str
    date: str


class LoanHistory(BaseModel):
    """Historique d'un prêt"""
    montant: float
    statut: str
    date_creation: str


class MemberContributions(BaseModel):
    """Contributions d'un membre"""
    total: float = 0
    count: int = 0
    historique: List[ContributionHistory] = []


class MemberLoans(BaseModel):
    """Prêts d'un membre"""
    total_emprunte: float = 0
    total_rembourse: float = 0
    en_cours: float = 0
    historique: List[LoanHistory] = []


class GroupInfo(BaseModel):
    """Informations sur le groupe"""
    montant_contribution: float
    frequence: str


class RiskScoreRequest(BaseModel):
    """Requête pour le calcul du score de risque"""
    user_id: int
    group_id: int
    date_adhesion: Optional[str] = None
    role: Optional[str] = "membre"
    contributions: MemberContributions = MemberContributions()
    prets: MemberLoans = MemberLoans()
    groupe: GroupInfo


class RiskScoreResponse(BaseModel):
    """Réponse du calcul de score de risque"""
    user_id: int
    group_id: int
    score: float = Field(ge=0, le=100)
    niveau_risque: str
    probabilite_defaut: float
    facteurs: List[Dict[str, Any]]
    recommandation: str
    details: Dict[str, Any]


class BatchRiskRequest(BaseModel):
    """Requête pour calcul de risque en lot"""
    group_id: int
    membres: List[RiskScoreRequest]


# =============================================================================
# ENDPOINTS
# =============================================================================

@router.post("/risk-score", response_model=RiskScoreResponse)
async def calculate_risk_score(request: RiskScoreRequest):
    """
    Calculer le score de risque de défaut d'un membre
    
    Le score est basé sur:
    - Régularité des contributions (40%)
    - Historique des prêts (30%)
    - Ancienneté dans le groupe (15%)
    - Ratio épargne/prêts (15%)
    
    Niveaux de risque:
    - 80-100: Faible (vert)
    - 60-79: Modéré (jaune)
    - 40-59: Élevé (orange)
    - 0-39: Critique (rouge)
    """
    try:
        result = risk_service.calculate_risk_score(request.dict())
        return RiskScoreResponse(
            user_id=request.user_id,
            group_id=request.group_id,
            **result
        )
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/risk-score/batch")
async def calculate_batch_risk_scores(request: BatchRiskRequest):
    """
    Calculer les scores de risque pour plusieurs membres d'un groupe
    """
    try:
        results = []
        for membre in request.membres:
            score_result = risk_service.calculate_risk_score(membre.dict())
            results.append({
                "user_id": membre.user_id,
                **score_result
            })
        
        # Trier par score (les plus risqués en premier)
        results.sort(key=lambda x: x["score"])
        
        # Statistiques globales
        scores = [r["score"] for r in results]
        stats = {
            "moyenne": sum(scores) / len(scores) if scores else 0,
            "minimum": min(scores) if scores else 0,
            "maximum": max(scores) if scores else 0,
            "membres_a_risque": sum(1 for s in scores if s < 60),
        }
        
        return {
            "group_id": request.group_id,
            "resultats": results,
            "statistiques": stats
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/risk-factors")
async def analyze_risk_factors(request: RiskScoreRequest):
    """
    Analyser en détail les facteurs de risque d'un membre
    """
    try:
        factors = risk_service.analyze_risk_factors(request.dict())
        return {
            "user_id": request.user_id,
            "group_id": request.group_id,
            "facteurs": factors
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/credit-limit")
async def calculate_credit_limit(request: RiskScoreRequest):
    """
    Calculer la limite de crédit recommandée pour un membre
    basée sur son score de risque et ses contributions
    """
    try:
        limit = risk_service.calculate_credit_limit(request.dict())
        return {
            "user_id": request.user_id,
            "group_id": request.group_id,
            "limite_credit": limit
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
