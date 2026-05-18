#!/bin/bash

#############################################################################
# Student Hub Deployment Script
#
# Usage: ./deploy.sh
#
# This script automates deployment for changes on the main branch.
# It performs the full update workflow including:
#   - Git pull from main
#   - Composer dependency installation
#   - Frontend asset building with Vite
#   - Wayfinder route helper generation
#   - Database migrations
#   - Cache clearing and rebuild
#   - Supervisor process restart
#
# Prerequisites:
#   - Run as www-data user (or use sudo)
#   - App directory: /opt/studenthub.usm.edu.ph/student-hub
#   - Git, Composer, npm, PHP already installed
#   - .env file configured with database credentials
#
#############################################################################

set -e  # Exit on any error

# Color output for readability
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
APP_DIR="/opt/studenthub.usm.edu.ph/student-hub"
BRANCH="main"
LOG_FILE="$APP_DIR/storage/logs/deploy.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Helper functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

# Check if running from correct directory or cd to app directory
if [ ! -d "$APP_DIR" ]; then
    log_error "Application directory not found: $APP_DIR"
    exit 1
fi

cd "$APP_DIR"
log_info "Working directory: $APP_DIR"

# Check if .env file exists
if [ ! -f "$APP_DIR/.env" ]; then
    log_error ".env file not found at $APP_DIR/.env"
    exit 1
fi

echo "" | tee -a "$LOG_FILE"
log_info "=========================================="
log_info "Starting deployment at $TIMESTAMP"
log_info "=========================================="
echo "" | tee -a "$LOG_FILE"

# Step 1: Pull latest code
log_info "Step 1/7: Pulling latest code from $BRANCH branch..."
if git pull origin "$BRANCH" >> "$LOG_FILE" 2>&1; then
    log_success "Git pull completed"
else
    log_error "Git pull failed"
    exit 1
fi

# Step 2: Install PHP dependencies
log_info "Step 2/7: Installing PHP dependencies..."
if composer install --no-dev --optimize-autoloader >> "$LOG_FILE" 2>&1; then
    log_success "Composer dependencies installed"
else
    log_error "Composer install failed"
    exit 1
fi

# Step 3: Install Node dependencies
log_info "Step 3/7: Installing Node dependencies..."
if npm ci >> "$LOG_FILE" 2>&1; then
    log_success "npm dependencies installed"
else
    log_warning "npm ci had warnings, continuing anyway"
fi

# Step 4: Generate Wayfinder routes
log_info "Step 4/7: Generating Wayfinder route helpers..."
if php artisan wayfinder:generate --no-interaction >> "$LOG_FILE" 2>&1; then
    log_success "Wayfinder routes generated"
else
    log_warning "Wayfinder generation had issues, continuing anyway"
fi

# Step 5: Build frontend assets
log_info "Step 5/7: Building frontend assets with Vite..."
if npm run build >> "$LOG_FILE" 2>&1; then
    log_success "Frontend assets built"
else
    log_error "npm build failed"
    exit 1
fi

# Step 6: Run database migrations
log_info "Step 6/7: Running database migrations..."
if php artisan migrate --force >> "$LOG_FILE" 2>&1; then
    log_success "Database migrations completed"
else
    log_error "Migration failed"
    exit 1
fi

# Step 7: Clear and rebuild caches
log_info "Step 7/7: Clearing and rebuilding caches..."
if php artisan config:cache >> "$LOG_FILE" 2>&1; then
    log_success "Config cache rebuilt"
else
    log_warning "Config cache had issues, continuing"
fi

if php artisan route:cache >> "$LOG_FILE" 2>&1; then
    log_success "Route cache rebuilt"
else
    log_warning "Route cache had issues, continuing"
fi

if php artisan view:cache >> "$LOG_FILE" 2>&1; then
    log_success "View cache rebuilt"
else
    log_warning "View cache had issues, continuing"
fi

# Restart Supervisor processes
log_info "Restarting Supervisor processes..."
if sudo supervisorctl restart student-hub-queue:* >> "$LOG_FILE" 2>&1; then
    log_success "Queue workers restarted"
else
    log_warning "Queue restart had issues"
fi

if sudo supervisorctl restart student-hub-scheduler >> "$LOG_FILE" 2>&1; then
    log_success "Scheduler restarted"
else
    log_warning "Scheduler restart had issues"
fi

# Final status
echo "" | tee -a "$LOG_FILE"
log_success "=========================================="
log_success "Deployment completed successfully!"
log_success "=========================================="
echo "" | tee -a "$LOG_FILE"

# Print supervisor status
log_info "Current Supervisor status:"
sudo supervisorctl status | tee -a "$LOG_FILE"

echo "" | tee -a "$LOG_FILE"
log_info "View logs: tail -f $LOG_FILE"
