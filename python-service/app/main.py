"""
TWUNGURANE - Microservice d'Analyse Financière
FastAPI Application principale

Ce service fournit des analyses financières avancées pour les groupes d'épargne:
- Calcul de risque de défaut
- Projections financières
- Score de santé des groupes
- Classement des membres
- Simulation de cycles d'épargne
"""

from fastapi import FastAPI, Depends, HTTPException, Security
from fastapi.middleware.cors import CORSMiddleware
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from contextlib import asynccontextmanager
import os
from loguru import logger

# Import des routers
from app.routers import analytics, risk, projections, health

# Configuration de la sécurité
security = HTTPBearer()

# Token interne pour la communication avec Laravel
INTERNAL_API_TOKEN = os.getenv("INTERNAL_API_TOKEN", "twungurane_internal_token_2024")


def verify_internal_token(credentials: HTTPAuthorizationCredentials = Security(security)):
    """
    Vérifie le token interne pour sécuriser la communication inter-services
    """
    if credentials.credentials != INTERNAL_API_TOKEN:
        logger.warning(f"Tentative d'accès avec token invalide")
        raise HTTPException(
            status_code=401,
            detail="Token d'authentification invalide"
        )
    return credentials.credentials


@asynccontextmanager
async def lifespan(app: FastAPI):
    """
    Gestion du cycle de vie de l'application
    """
    # Démarrage
    logger.info("🚀 Démarrage du microservice TWUNGURANE Analytics")
    logger.info(f"📊 Environnement: {os.getenv('ENVIRONMENT', 'development')}")
    yield
    # Arrêt
    logger.info("🛑 Arrêt du microservice TWUNGURANE Analytics")


# Création de l'application FastAPI
app = FastAPI(
    title="TWUNGURANE Analytics API",
    description="""
    ## Microservice d'Analyse Financière pour les Groupes d'Épargne
    
    Ce service fournit des analyses avancées pour la plateforme TWUNGURANE:
    
    - **Calcul de Risque** : Évaluation du risque de défaut des membres
    - **Projections Financières** : Simulation de l'évolution des épargnes
    - **Santé des Groupes** : Indicateurs de performance des VSLA
    - **Classement Membres** : Scoring basé sur la discipline financière
    - **Simulation de Cycles** : Projection des cycles d'épargne
    
    ### Authentification
    
    Toutes les routes nécessitent un token Bearer interne pour la communication avec le service Laravel.
    """,
    version="1.0.0",
    docs_url="/docs",
    redoc_url="/redoc",
    lifespan=lifespan
)

# Configuration CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # En production, restreindre aux domaines autorisés
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Inclusion des routers
app.include_router(
    analytics.router,
    prefix="/api/v1",
    tags=["Analytics"],
    dependencies=[Depends(verify_internal_token)]
)

app.include_router(
    risk.router,
    prefix="/api/v1",
    tags=["Risk Assessment"],
    dependencies=[Depends(verify_internal_token)]
)

app.include_router(
    projections.router,
    prefix="/api/v1",
    tags=["Financial Projections"],
    dependencies=[Depends(verify_internal_token)]
)

app.include_router(
    health.router,
    prefix="/api/v1",
    tags=["Group Health"],
    dependencies=[Depends(verify_internal_token)]
)


# ============================================================================
# Routes publiques
# ============================================================================

@app.get("/", tags=["Status"])
async def root():
    """
    Point d'entrée principal - Informations sur le service
    """
    return {
        "service": "TWUNGURANE Analytics API",
        "version": "1.0.0",
        "status": "running",
        "documentation": "/docs",
        "description": "Microservice d'analyse financière pour les groupes d'épargne communautaire"
    }


@app.get("/health", tags=["Status"])
async def health_check():
    """
    Vérification de l'état de santé du service
    """
    return {
        "status": "healthy",
        "service": "twungurane-analytics",
        "version": "1.0.0"
    }


@app.get("/api/v1/status", tags=["Status"])
async def api_status():
    """
    État de l'API avec informations détaillées
    """
    return {
        "status": "operational",
        "endpoints": {
            "risk_score": "/api/v1/risk-score",
            "financial_projection": "/api/v1/financial-projection",
            "group_health": "/api/v1/group-health",
            "member_ranking": "/api/v1/member-ranking/{group_id}",
            "cycle_simulation": "/api/v1/cycle-simulation"
        },
        "documentation": {
            "swagger": "/docs",
            "redoc": "/redoc"
        }
    }


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "app.main:app",
        host="0.0.0.0",
        port=8000,
        reload=True
    )
