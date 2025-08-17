<!-- Implementation Guide for Your Developer
1. System Architecture Overview
The voting system consists of two parts:

Python Flask API: Handles voting logic, email notifications, and database operations
Laravel Integration: Provides admin interface and integrates with your existing website

2. Step-by-Step Implementation
Phase 1: Python API Setup

Server Requirements -->

# Create a new directory for the Python API
mkdir grant-voting-api
cd grant-voting-api

# Create virtual environment
python3 -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate

# Install required packages
pip install flask flask-sqlalchemy flask-mail python-dotenv

<!-- Environment Configuration
Create .env file: -->

SECRET_KEY=your-very-secret-key-here
MAIL_SERVER=smtp.gmail.com
MAIL_PORT=587
MAIL_USE_TLS=True
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
DATABASE_URL=sqlite:///voting_system.db

<!-- 
Deploy Python API

Save the Python code I provided as app.py
Run with: python app.py (development) or use Gunicorn for production
The API will run on http://localhost:5000 by default



Phase 2: Laravel Integration

Add Configuration to Laravel
In config/services.php: -->

'voting' => [
    'api_url' => env('VOTING_API_URL', 'http://localhost:5000'),
],


<!-- In .env: -->

VOTING_API_URL=http://your-python-api-domain.com

<!-- Create Laravel Service and Controller
Run these commands: -->

php artisan make:service GrantVotingService
php artisan make:controller GrantVotingController


<!-- Then use the PHP code I provided above. -->

<!-- Add Routes
In routes/web.php: -->

Route::prefix('admin/voting')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [GrantVotingController::class, 'index'])->name('voting.index');
    Route::get('/create', [GrantVotingController::class, 'createApplication'])->name('voting.create');
    Route::post('/store', [GrantVotingController::class, 'storeApplication'])->name('voting.store');
    Route::get('/results/{id}', [GrantVotingController::class, 'showResults'])->name('voting.results');
    Route::get('/members', [GrantVotingController::class, 'manageMembers'])->name('voting.members');
    Route::post('/members', [GrantVotingController::class, 'storeMember'])->name('voting.members.store');
    Route::put('/members/{id}', [GrantVotingController::class, 'updateMember'])->name('voting.members.update');
});

<!-- 
Phase 3: Frontend Views
Create these Blade templates:

Main Dashboard (resources/views/admin/voting/index.blade.php): -->

@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h3>Grant Voting System</h3>
                    <div>
                        <a href="{{ route('voting.create') }}" class="btn btn-primary">New Application</a>
                        <a href="{{ route('voting.members') }}" class="btn btn-secondary">Manage Members</a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Applications list here -->
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

<!-- 
Create Application Form (resources/views/admin/voting/create-application.blade.php): -->

@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>Create Grant Application for Voting</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('voting.store') }}" method="POST">
                        @csrf
                        
                        <div class="form-group mb-3">
                            <label>Your Name and Surname</label>
                            <input type="text" name="submitter_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label>Candidate's Full Name</label>
                            <input type="text" name="candidate_full_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label>Type of Grant Application</label>
                            <select name="grant_type" class="form-control" required>
                                <option value="">Select Grant Type</option>
                                <option value="STSM">STSM</option>
                                <option value="DISSEMINATION_CONFERENCE">Dissemination Conference Grant</option>
                                <option value="ITC_CONFERENCE">ITC Conference Grant</option>
                                <option value="YRI_CONFERENCE">Young Researchers and Innovators (YRI) Conference Grants</option>
                            </select>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label>Date</label>
                            <input type="date" name="date" class="form-control" required>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label>Place</label>
                            <input type="text" name="place" class="form-control" required>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label>Amount Requested</label>
                                <input type="number" name="amount_requested" class="form-control" step="0.01" required>
                            </div>
                            <div class="col-md-4">
                                <label>Currency</label>
                                <select name="currency" class="form-control">
                                    <option value="EUR">EUR</option>
                                    <option value="USD">USD</option>
                                    <option value="GBP">GBP</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label>Description (Optional)</label>
                            <textarea name="description" class="form-control" rows="4"></textarea>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label>Voting Deadline</label>
                            <input type="datetime-local" name="voting_deadline" class="form-control" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Create Application & Send Voting Emails</button>
                        <a href="{{ route('voting.index') }}" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


<!-- Phase 4: Member Management
Create member management interface: -->

<!-- resources/views/admin/voting/members.blade.php -->
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h3>Voting Members Management</h3>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                        Add New Member
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Position</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="membersTable">
                                <!-- Members will be loaded here via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Voting Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addMemberForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Position</label>
                        <input type="text" class="form-control" name="position" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Load members and handle form submissions
document.addEventListener('DOMContentLoaded', function() {
    loadMembers();
    
    document.getElementById('addMemberForm').addEventListener('submit', function(e) {
        e.preventDefault();
        // Add member logic here
    });
});

function loadMembers() {
    fetch('/admin/voting/api/members')
        .then(response => response.json())
        .then(members => {
            const tbody = document.getElementById('membersTable');
            tbody.innerHTML = '';
            
            members.forEach(member => {
                const row = `
                    <tr>
                        <td>${member.name}</td>
                        <td>${member.position}</td>
                        <td>${member.email}</td>
                        <td>
                            <span class="badge ${member.is_active ? 'bg-success' : 'bg-secondary'}">
                                ${member.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editMember(${member.id})">Edit</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deactivateMember(${member.id})">Deactivate</button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        });
}
</script>
@endsection

<!-- 
3. Deployment Instructions
For Production:

Python API Deployment: -->

# Install Gunicorn
pip install gunicorn

# Run with Gunicorn
gunicorn -w 4 -b 0.0.0.0:5000 app:app

<!-- Nginx Configuration: -->


server {
    listen 80;
    server_name your-voting-api-domain.com;
    
    location / {
        proxy_pass http://127.0.0.1:5000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}

<!-- Process Manager (PM2 or systemd): -->

# Using PM2
pm2 start "gunicorn -w 4 -b 0.0.0.0:5000 app:app" --name voting-api



<!-- 4. Key Features Implemented
✅ Email notifications to all voting members
✅ Accept/Reject voting with rejection reasons
✅ Comments and discussion system
✅ Member management (add/edit/deactivate)
✅ Voting results dashboard
✅ Integration with Laravel admin panel
✅ Unique voting tokens for security
✅ Deadline management
✅ Persian/English support ready
5. Testing Checklist

 Create test grant application
 Verify emails are sent to all members
 Test voting process with different members
 Check rejection reasons are saved
 Test member management functions
 Verify results display correctly
 Test deadline enforcement

6. Security Notes

Each voter gets a unique token
Voting deadline is enforced
Only active members can vote
Email verification through unique links
Admin panel requires authentication -->