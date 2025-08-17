# <!-- Complete Guide: -->

# <!-- Step 1: Python API Setup (Backend) -->

# <!-- Create project structure: -->

mkdir grant-voting-system
cd grant-voting-system
python3 -m venv venv
source venv/bin/activate  # Windows: venv\Scripts\activate
pip install flask flask-sqlalchemy flask-mail python-dotenv gunicorn

# Save the Python code as app.py (from my first artifact)
# Create .env file:

SECRET_KEY=your-super-secret-key-change-this
MAIL_SERVER=smtp.gmail.com
MAIL_PORT=587
MAIL_USE_TLS=True
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
DATABASE_URL=sqlite:///voting_system.db

# Step 2: Laravel Integration

# Add to config/services.php:

'voting' => [
    'api_url' => env('VOTING_API_URL', 'http://localhost:5000'),
],

# Add to Laravel .env:

VOTING_API_URL=http://your-python-api-domain.com

# Create Laravel files:

php artisan make:service GrantVotingService
php artisan make:controller GrantVotingController
php artisan make:middleware AdminMiddleware

# Copy the PHP code from my Laravel integration artifact into respective files
# Add routes to routes/web.php:

Route::prefix('admin/voting')->middleware(['auth', 'admin'])->name('voting.')->group(function () {
    Route::get('/', [GrantVotingController::class, 'index'])->name('index');
    Route::get('/create', [GrantVotingController::class, 'createApplication'])->name('create');
    Route::post('/store', [GrantVotingController::class, 'storeApplication'])->name('store');
    Route::get('/results/{id}', [GrantVotingController::class, 'showResults'])->name('results');
    Route::get('/members', [GrantVotingController::class, 'manageMembers'])->name('members');
    
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/members', [GrantVotingController::class, 'getMembers'])->name('members.get');
        Route::post('/members', [GrantVotingController::class, 'storeMember'])->name('members.store');
        Route::put('/members/{id}', [GrantVotingController::class, 'updateMember'])->name('members.update');
        Route::delete('/members/{id}', [GrantVotingController::class, 'deactivateMember'])->name('members.delete');
    });
});

# Create Blade templates using my template artifact code

# Step 3: Navigation Integration
# Add to your Laravel admin sidebar:

{{-- In your admin layout sidebar --}}
<li class="nav-item">
    <a class="nav-link" href="{{ route('voting.index') }}">
        <i class="fas fa-vote-yea"></i>
        Grant Voting
    </a>
</li>

# Step 4: Production Deployment
# For Python API:

# Install Gunicorn
pip install gunicorn

# Run in production
gunicorn -w 4 -b 0.0.0.0:5000 app:app

# Or with PM2
pm2 start "gunicorn -w 4 -b 0.0.0.0:5000 app:app" --name voting-api

# Nginx config:

server {
    listen 80;
    server_name voting-api.yourdomain.com;
    
    location / {
        proxy_pass http://127.0.0.1:5000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}


# Step 5: Testing Checklist
# ✅ Basic functionality:

#  Create grant application
#  Verify emails sent to members
#  Test voting process
#  Check rejection reasons
#  View results dashboard

# ✅ Member management:

#  Add new member
#  Edit existing member
#  Deactivate member
#  Verify email updates

# ✅ Security:

#  Unique voting tokens work
#  Deadline enforcement works
#  Admin access only

# Step 6: Key Features Delivered
# 🎯 Everything you requested:

# ✅ Email notifications to all committee members
# ✅ Accept/Reject voting with mandatory rejection reasons
# ✅ Comments and discussion system
# ✅ Easy member management (add/edit/replace members)
# ✅ Voting results dashboard
# ✅ Integration with Laravel admin panel
# ✅ Persian text support ready
# ✅ Unique reference codes (CA######)
# ✅ Deadline management
# ✅ All grant types supported

# Step 7: File Structure Summary


The Laravel Project/
├── app/
│   ├── Http/Controllers/GrantVotingController.php
│   ├── Services/GrantVotingService.php
│   └── Http/Middleware/AdminMiddleware.php
├── resources/views/admin/voting/
│   ├── index.blade.php
│   ├── create-application.blade.php
│   ├── results.blade.php
│   └── members.blade.php
├── routes/web.php (updated)
└── config/services.php (updated)

Separate Python API/
├── app.py (main Flask application)
├── .env (configuration)
├── requirements.txt
└── voting_system.db (SQLite database)