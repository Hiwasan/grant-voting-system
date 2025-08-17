<?php
// Laravel Integration Helper for Grant Voting System
// File: app/Services/GrantVotingService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GrantVotingService
{
    private $pythonApiUrl;
    
    public function __construct()
    {
        $this->pythonApiUrl = config('services.voting.api_url', 'http://localhost:5000');
    }
    
    /**
     * Create a new grant application for voting
     */
    public function createVotingApplication($data)
    {
        try {
            $response = Http::post($this->pythonApiUrl . '/api/applications', [
                'submitter_name' => $data['submitter_name'],
                'candidate_full_name' => $data['candidate_full_name'],
                'grant_type' => $data['grant_type'],
                'date' => $data['date'],
                'place' => $data['place'],
                'amount_requested' => $data['amount_requested'],
                'currency' => $data['currency'] ?? 'EUR',
                'description' => $data['description'] ?? '',
                'voting_deadline' => $data['voting_deadline']
            ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            Log::error('Failed to create voting application', ['response' => $response->body()]);
            return false;
            
        } catch (\Exception $e) {
            Log::error('Error creating voting application', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Get application details and voting results
     */
    public function getApplicationResults($applicationId)
    {
        try {
            $response = Http::get($this->pythonApiUrl . '/api/results/' . $applicationId);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Error getting application results', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Get all voting members
     */
    public function getVotingMembers()
    {
        try {
            $response = Http::get($this->pythonApiUrl . '/api/members');
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Error getting voting members', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Add new voting member
     */
    public function addVotingMember($memberData)
    {
        try {
            $response = Http::post($this->pythonApiUrl . '/api/members', $memberData);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Error adding voting member', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Update voting member
     */
    public function updateVotingMember($memberId, $memberData)
    {
        try {
            $response = Http::put($this->pythonApiUrl . '/api/members/' . $memberId, $memberData);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Error updating voting member', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Deactivate voting member
     */
    public function deactivateVotingMember($memberId)
    {
        try {
            $response = Http::delete($this->pythonApiUrl . '/api/members/' . $memberId);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Error deactivating voting member', ['error' => $e->getMessage()]);
            return false;
        }
    }
}

// Laravel Controller
// File: app/Http/Controllers/GrantVotingController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GrantVotingService;
use Illuminate\Support\Facades\Validator;

class GrantVotingController extends Controller
{
    private $votingService;
    
    public function __construct(GrantVotingService $votingService)
    {
        $this->votingService = $votingService;
    }
    
    /**
     * Show the voting management dashboard
     */
    public function index()
    {
        $members = $this->votingService->getVotingMembers();
        return view('admin.voting.index', compact('members'));
    }
    
    /**
     * Show form to create new grant application for voting
     */
    public function createApplication()
    {
        return view('admin.voting.create-application');
    }
    
    /**
     * Store new grant application for voting
     */
    public function storeApplication(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'submitter_name' => 'required|string|max:100',
            'candidate_full_name' => 'required|string|max:100',
            'grant_type' => 'required|in:STSM,DISSEMINATION_CONFERENCE,ITC_CONFERENCE,YRI_CONFERENCE',
            'date' => 'required|date',
            'place' => 'required|string|max:200',
            'amount_requested' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'description' => 'nullable|string',
            'voting_deadline' => 'required|date|after:now'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $result = $this->votingService->createVotingApplication($request->all());

        if ($result) {
            return redirect()->route('voting.index')
                ->with('success', 'Grant application created successfully! Reference: ' . $result['reference_code'] . '. Voting emails have been sent to all members.');
        }

        return redirect()->back()
            ->with('error', 'Failed to create voting application. Please try again.')
            ->withInput();
    }
    
    /**
     * Show voting results for an application
     */
    public function showResults($applicationId)
    {
        $results = $this->votingService->getApplicationResults($applicationId);
        
        if (!$results) {
            return redirect()->route('voting.index')
                ->with('error', 'Application not found or results unavailable.');
        }
        
        return view('admin.voting.results', compact('results'));
    }
    
    /**
     * Show member management page
     */
    public function manageMembers()
    {
        $members = $this->votingService->getVotingMembers();
        return view('admin.voting.members', compact('members'));
    }
    
    /**
     * Store new voting member
     */
    public function storeMember(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'position' => 'required|string|max:100',
            'email' => 'required|email|max:120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->votingService->addVotingMember($request->all());

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Member added successfully',
                'member' => $result
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to add member'
        ], 500);
    }
    
    /**
     * Update voting member
     */
    public function updateMember(Request $request, $memberId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'position' => 'required|string|max:100',
            'email' => 'required|email|max:120',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->votingService->updateVotingMember($memberId, $request->all());

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Member updated successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to update member'
        ], 500);
    }
    
    /**
     * Deactivate voting member
     */
    public function deactivateMember($memberId)
    {
        $result = $this->votingService->deactivateVotingMember($memberId);

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Member deactivated successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to deactivate member'
        ], 500);
    }
    
    /**
     * API endpoint to get members (for AJAX requests)
     */
    public function getMembers()
    {
        $members = $this->votingService->getVotingMembers();
        return response()->json($members ?: []);
    }
    
    /**
     * API endpoint to get application details
     */
    public function getApplication($applicationId)
    {
        $application = $this->votingService->getApplicationResults($applicationId);
        return response()->json($application ?: []);
    }
}

// Additional Laravel Migration for local tracking (optional)
// File: database/migrations/2024_01_01_000000_create_voting_applications_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVotingApplicationsTable extends Migration
{
    public function up()
    {
        Schema::create('voting_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->string('submitter_name');
            $table->string('candidate_full_name');
            $table->enum('grant_type', ['STSM', 'DISSEMINATION_CONFERENCE', 'ITC_CONFERENCE', 'YRI_CONFERENCE']);
            $table->date('event_date');
            $table->string('place');
            $table->decimal('amount_requested', 10, 2);
            $table->string('currency', 10)->default('EUR');
            $table->text('description')->nullable();
            $table->datetime('voting_deadline');
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->json('voting_results')->nullable(); // Store final results
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('voting_applications');
    }
}

// Laravel Model (optional for local tracking)
// File: app/Models/VotingApplication.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VotingApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_code',
        'submitter_name',
        'candidate_full_name',
        'grant_type',
        'event_date',
        'place',
        'amount_requested',
        'currency',
        'description',
        'voting_deadline',
        'status',
        'voting_results'
    ];

    protected $casts = [
        'event_date' => 'date',
        'voting_deadline' => 'datetime',
        'voting_results' => 'array'
    ];
    
    public function getGrantTypeDisplayAttribute()
    {
        $types = [
            'STSM' => 'STSM',
            'DISSEMINATION_CONFERENCE' => 'Dissemination Conference Grant',
            'ITC_CONFERENCE' => 'ITC Conference Grant',
            'YRI_CONFERENCE' => 'Young Researchers and Innovators (YRI) Conference Grants'
        ];
        
        return $types[$this->grant_type] ?? $this->grant_type;
    }
}

// Laravel Middleware for admin access
// File: app/Http/Middleware/AdminMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check() || !Auth::user()->is_admin) {
            return redirect('/')->with('error', 'Access denied. Admin privileges required.');
        }

        return $next($request);
    }
}

// Register the middleware in app/Http/Kernel.php
// Add to $routeMiddleware array:
// 'admin' => \App\Http\Middleware\AdminMiddleware::class,

// Routes file update
// File: routes/web.php (complete routing)

use App\Http\Controllers\GrantVotingController;

Route::prefix('admin/voting')->middleware(['auth', 'admin'])->name('voting.')->group(function () {
    // Main pages
    Route::get('/', [GrantVotingController::class, 'index'])->name('index');
    Route::get('/create', [GrantVotingController::class, 'createApplication'])->name('create');
    Route::post('/store', [GrantVotingController::class, 'storeApplication'])->name('store');
    Route::get('/results/{id}', [GrantVotingController::class, 'showResults'])->name('results');
    Route::get('/members', [GrantVotingController::class, 'manageMembers'])->name('members');
    
    // API endpoints for AJAX
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/members', [GrantVotingController::class, 'getMembers'])->name('members.get');
        Route::post('/members', [GrantVotingController::class, 'storeMember'])->name('members.store');
        Route::put('/members/{id}', [GrantVotingController::class, 'updateMember'])->name('members.update');
        Route::delete('/members/{id}', [GrantVotingController::class, 'deactivateMember'])->name('members.delete');
        Route::get('/applications/{id}', [GrantVotingController::class, 'getApplication'])->name('applications.get');
    });
});

// Configuration file
// File: config/voting.php

return [
    'api_url' => env('VOTING_API_URL', 'http://localhost:5000'),
    'timeout' => env('VOTING_API_TIMEOUT', 30),
    
    'grant_types' => [
        'STSM' => 'STSM',
        'DISSEMINATION_CONFERENCE' => 'Dissemination Conference Grant',
        'ITC_CONFERENCE' => 'ITC Conference Grant',
        'YRI_CONFERENCE' => 'Young Researchers and Innovators (YRI) Conference Grants'
    ],
    
    'default_positions' => [
        'Action Chair',
        'Action Vice Chair',
        'Grant Holder Scientific Representative',
        'Science Communication Coordinator',
        'Grant Awarding Coordinator',
        'WG1 Leader',
        'WG1 Vice Leader',
        'WG2 Leader',
        'WG2 Vice Leader',
        'WG3 Leader',
        'WG3 Vice Leader',
        'WG4 Leader',
        'WG4 Vice Leader',
        'WG5 Leader',
        'WG5 Vice Leader'
    ]
];

// Enhanced Service with error handling and caching
// File: app/Services/GrantVotingService.php (Enhanced version)

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GrantVotingService
{
    private $pythonApiUrl;
    private $timeout;
    
    public function __construct()
    {
        $this->pythonApiUrl = config('voting.api_url', 'http://localhost:5000');
        $this->timeout = config('voting.timeout', 30);
    }
    
    /**
     * Create a new grant application for voting
     */
    public function createVotingApplication($data)
    {
        try {
            $response = Http::timeout($this->timeout)->post($this->pythonApiUrl . '/api/applications', [
                'submitter_name' => $data['submitter_name'],
                'candidate_full_name' => $data['candidate_full_name'],
                'grant_type' => $data['grant_type'],
                'date' => $data['date'],
                'place' => $data['place'],
                'amount_requested' => floatval($data['amount_requested']),
                'currency' => $data['currency'] ?? 'EUR',
                'description' => $data['description'] ?? '',
                'voting_deadline' => date('Y-m-d H:i', strtotime($data['voting_deadline']))
            ]);
            
            if ($response->successful()) {
                // Clear cache
                Cache::forget('voting_members');
                return $response->json();
            }
            
            Log::error('Failed to create voting application', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            return false;
            
        } catch (\Exception $e) {
            Log::error('Error creating voting application', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Get application details and voting results
     */
    public function getApplicationResults($applicationId)
    {
        try {
            $cacheKey = "voting_results_{$applicationId}";
            
            return Cache::remember($cacheKey, 300, function () use ($applicationId) {
                $response = Http::timeout($this->timeout)->get($this->pythonApiUrl . '/api/results/' . $applicationId);
                
                if ($response->successful()) {
                    return $response->json();
                }
                
                Log::error('Failed to get application results', [
                    'application_id' => $applicationId,
                    'status' => $response->status()
                ]);
                return false;
            });
            
        } catch (\Exception $e) {
            Log::error('Error getting application results', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Get all voting members with caching
     */
    public function getVotingMembers()
    {
        try {
            return Cache::remember('voting_members', 600, function () {
                $response = Http::timeout($this->timeout)->get($this->pythonApiUrl . '/api/members');
                
                if ($response->successful()) {
                    return $response->json();
                }
                
                Log::error('Failed to get voting members', ['status' => $response->status()]);
                return false;
            });
            
        } catch (\Exception $e) {
            Log::error('Error getting voting members', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Add new voting member
     */
    public function addVotingMember($memberData)
    {
        try {
            $response = Http::timeout($this->timeout)->post($this->pythonApiUrl . '/api/members', [
                'name' => $memberData['name'],
                'position' => $memberData['position'],
                'email' => $memberData['email']
            ]);
            
            if ($response->successful()) {
                Cache::forget('voting_members');
                return $response->json();
            }
            
            Log::error('Failed to add voting member', ['status' => $response->status()]);
            return false;
            
        } catch (\Exception $e) {
            Log::error('Error adding voting member', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Update voting member
     */
    public function updateVotingMember($memberId, $memberData)
    {
        try {
            $response = Http::timeout($this->timeout)->put($this->pythonApiUrl . '/api/members/' . $memberId, $memberData);
            
            if ($response->successful()) {
                Cache::forget('voting_members');
                return $response->json();
            }
            
            Log::error('Failed to update voting member', ['member_id' => $memberId, 'status' => $response->status()]);
            return false;
            
        } catch (\Exception $e) {
            Log::error('Error updating voting member', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Deactivate voting member
     */
    public function deactivateVotingMember($memberId)
    {
        try {
            $response = Http::timeout($this->timeout)->delete($this->pythonApiUrl . '/api/members/' . $memberId);
            
            if ($response->successful()) {
                Cache::forget('voting_members');
                return $response->json();
            }
            
            Log::error('Failed to deactivate voting member', ['member_id' => $memberId, 'status' => $response->status()]);
            return false;
            
        } catch (\Exception $e) {
            Log::error('Error deactivating voting member', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Health check for Python API
     */
    public function healthCheck()
    {
        try {
            $response = Http::timeout(5)->get($this->pythonApiUrl . '/api/health');
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}

{{-- resources/views/admin/voting/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Grant Voting System')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Grant Voting System Dashboard</h3>
                    <div>
                        <a href="{{ route('voting.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Application
                        </a>
                        <a href="{{ route('voting.members') }}" class="btn btn-secondary">
                            <i class="fas fa-users"></i> Manage Members
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h5>Active Applications</h5>
                                    <h2 id="activeCount">-</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h5>Completed Votes</h5>
                                    <h2 id="completedCount">-</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h5>Total Members</h5>
                                    <h2 id="membersCount">{{ is_array($members) ? count($members) : 0 }}</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h5>Pending Votes</h5>
                                    <h2 id="pendingCount">-</h2>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped" id="applicationsTable">
                            <thead>
                                <tr>
                                    <th>Reference Code</th>
                                    <th>Candidate</th>
                                    <th>Grant Type</th>
                                    <th>Amount</th>
                                    <th>Deadline</th>
                                    <th>Status</th>
                                    <th>Votes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        Loading applications...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadApplications();
    
    // Refresh every 30 seconds
    setInterval(loadApplications, 30000);
});

function loadApplications() {
    // This would typically load from your Laravel backend
    // For now, showing structure
    console.log('Loading applications...');
}

function viewResults(applicationId) {
    window.location.href = `/admin/voting/results/${applicationId}`;
}
</script>
@endsection

{{-- resources/views/admin/voting/create-application.blade.php --}}
@extends('layouts.admin')

@section('title', 'Create Grant Application')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>Create Grant Application for Voting</h3>
                    <p class="mb-0 text-muted">Fill in the details below to create a new grant application for voting. All committee members will receive email notifications.</p>
                </div>
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('voting.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Your Name and Surname <span class="text-danger">*</span></label>
                                    <input type="text" name="submitter_name" class="form-control" value="{{ old('submitter_name') }}" required>
                                    <small class="form-text text-muted">The person submitting this application</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Candidate's Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="candidate_full_name" class="form-control" value="{{ old('candidate_full_name') }}" required>
                                    <small class="form-text text-muted">The person applying for the grant</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label class="form-label">Type of Grant Application <span class="text-danger">*</span></label>
                            <select name="grant_type" class="form-control" required>
                                <option value="">Select Grant Type</option>
                                <option value="STSM" {{ old('grant_type') == 'STSM' ? 'selected' : '' }}>STSM</option>
                                <option value="DISSEMINATION_CONFERENCE" {{ old('grant_type') == 'DISSEMINATION_CONFERENCE' ? 'selected' : '' }}>Dissemination Conference Grant</option>
                                <option value="ITC_CONFERENCE" {{ old('grant_type') == 'ITC_CONFERENCE' ? 'selected' : '' }}>ITC Conference Grant</option>
                                <option value="YRI_CONFERENCE" {{ old('grant_type') == 'YRI_CONFERENCE' ? 'selected' : '' }}>Young Researchers and Innovators (YRI) Conference Grants</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Date <span class="text-danger">*</span></label>
                                    <input type="date" name="date" class="form-control" value="{{ old('date') }}" required>
                                    <small class="form-text text-muted">Event/travel date</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Place <span class="text-danger">*</span></label>
                                    <input type="text" name="place" class="form-control" value="{{ old('place') }}" required>
                                    <small class="form-text text-muted">Location of event/travel</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group mb-3">
                                    <label class="form-label">Amount Requested <span class="text-danger">*</span></label>
                                    <input type="number" name="amount_requested" class="form-control" step="0.01" min="0" value="{{ old('amount_requested') }}" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label class="form-label">Currency</label>
                                    <select name="currency" class="form-control">
                                        <option value="EUR" {{ old('currency', 'EUR') == 'EUR' ? 'selected' : '' }}>EUR</option>
                                        <option value="USD" {{ old('currency') == 'USD' ? 'selected' : '' }}>USD</option>
                                        <option value="GBP" {{ old('currency') == 'GBP' ? 'selected' : '' }}>GBP</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label class="form-label">Description (Optional)</label>
                            <textarea name="description" class="form-control" rows="4" placeholder="Additional details about the grant application...">{{ old('description') }}</textarea>
                        </div>
                        
                        <div class="form-group mb-4">
                            <label class="form-label">Voting Deadline <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="voting_deadline" class="form-control" value="{{ old('voting_deadline') }}" required>
                            <small class="form-text text-muted">Members must vote before this date and time</small>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Create Application & Send Voting Emails
                            </button>
                            <a href="{{ route('voting.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Voting Members</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Voting emails will be sent to all active members:</p>
                    <div id="membersList">
                        @if(is_array($members))
                            @foreach($members as $member)
                                @if($member['is_active'])
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-user-check text-success me-2"></i>
                                        <div>
                                            <strong>{{ $member['name'] }}</strong><br>
                                            <small class="text-muted">{{ $member['position'] }}</small>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h5>Information</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li><i class="fas fa-info-circle text-info me-2"></i> Each member receives a unique voting link</li>
                        <li><i class="fas fa-clock text-warning me-2"></i> Voting deadline is enforced automatically</li>
                        <li><i class="fas fa-comments text-primary me-2"></i> Members can discuss and comment</li>
                        <li><i class="fas fa-chart-bar text-success me-2"></i> Real-time voting results available</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

{{-- resources/views/admin/voting/results.blade.php --}}
@extends('layouts.admin')

@section('title', 'Voting Results')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-1">Voting Results: {{ $results['application']['reference_code'] ?? 'N/A' }}</h3>
                        <p class="mb-0 text-muted">{{ $results['application']['candidate_full_name'] ?? 'Unknown Candidate' }}</p>
                    </div>
                    <a href="{{ route('voting.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
                <div class="card-body">
                    @if($results)
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h2>{{ $results['summary']['accept'] ?? 0 }}</h2>
                                        <p class="mb-0">Accept Votes</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-danger text-white">
                                    <div class="card-body text-center">
                                        <h2>{{ $results['summary']['reject'] ?? 0 }}</h2>
                                        <p class="mb-0">Reject Votes</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h2>{{ $results['summary']['voted'] ?? 0 }}/{{ $results['summary']['total_voters'] ?? 0 }}</h2>
                                        <p class="mb-0">Response Rate</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-{{ ($results['summary']['accept'] ?? 0) > ($results['summary']['reject'] ?? 0) ? 'success' : 'danger' }} text-white">
                                    <div class="card-body text-center">
                                        <h4>{{ ($results['summary']['accept'] ?? 0) > ($results['summary']['reject'] ?? 0) ? 'APPROVED' : 'REJECTED' }}</h4>
                                        <p class="mb-0">Current Status</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Application Details --}}
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5>Application Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Grant Type:</strong> {{ $results['application']['grant_type'] ?? 'N/A' }}</p>
                                        <p><strong>Candidate:</strong> {{ $results['application']['candidate_full_name'] ?? 'N/A' }}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Reference:</strong> {{ $results['application']['reference_code'] ?? 'N/A' }}</p>
                                        <p><strong>Amount:</strong> {{ $results['application']['amount_requested'] ?? 'N/A' }} {{ $results['application']['currency'] ?? 'EUR' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Individual Votes --}}
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5>Individual Votes</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Member</th>
                                                <th>Position</th>
                                                <th>Vote</th>
                                                <th>Voted At</th>
                                                <th>Comments</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if(isset($results['votes']) && is_array($results['votes']))
                                                @foreach($results['votes'] as $vote)
                                                    <tr>
                                                        <td>{{ $vote['voter_name'] ?? 'Unknown' }}</td>
                                                        <td>{{ $vote['voter_position'] ?? 'Unknown' }}</td>
                                                        <td>
                                                            <span class="badge bg-{{ $vote['vote'] == 'Accept' ? 'success' : 'danger' }}">
                                                                {{ $vote['vote'] ?? 'Not Voted' }}
                                                            </span>
                                                        </td>
                                                        <td>{{ isset($vote['voted_at']) ? date('Y-m-d H:i', strtotime($vote['voted_at'])) : 'Not voted' }}</td>
                                                        <td>
                                                            @if(isset($vote['rejection_reason']) && $vote['rejection_reason'])
                                                                <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#reasonModal{{ $loop->index }}">
                                                                    View Reason
                                                                </button>
                                                                
                                                                {{-- Reason Modal --}}
                                                                <div class="modal fade" id="reasonModal{{ $loop->index }}" tabindex="-1">
                                                                    <div class="modal-dialog">
                                                                        <div class="modal-content">
                                                                            <div class="modal-header">
                                                                                <h5 class="modal-title">Rejection Reason - {{ $vote['voter_name'] }}</h5>
                                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                            </div>
                                                                            <div class="modal-body">
                                                                                <p>{{ $vote['rejection_reason'] }}</p>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @else
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">No votes recorded yet</td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{-- Rejection Reasons Summary --}}
                        @if(isset($results['rejection_reasons']) && count($results['rejection_reasons']) > 0)
                        <div class="card">
                            <div class="card-header">
                                <h5>Rejection Reasons Summary</h5>
                            </div>
                            <div class="card-body">
                                @foreach($results['rejection_reasons'] as $reason)
                                    @if($reason)
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            {{ $reason }}
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        @endif

                    @else
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Unable to load voting results. Please try again later.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

{{-- resources/views/admin/voting/members.blade.php --}}
@extends('layouts.admin')

@section('title', 'Manage Voting Members')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Voting Members Management</h3>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                            <i class="fas fa-user-plus"></i> Add New Member
                        </button>
                        <a href="{{ route('voting.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="membersTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Position</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        Loading members...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add Member Modal --}}
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
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Position <span class="text-danger">*</span></label>
                        <select class="form-control" name="position" required>
                            <option value="">Select Position</option>
                            @foreach(config('voting.default_positions', []) as $position)
                                <option value="{{ $position }}">{{ $position }}</option>
                            @endforeach
                            <option value="custom">Custom Position...</option>
                        </select>
                        <input type="text" class="form-control mt-2 d-none" name="custom_position" placeholder="Enter custom position">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
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

{{-- Edit Member Modal --}}
<div class="modal fade" id="editMemberModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Voting Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editMemberForm">
                <div class="modal-body">
                    <input type="hidden" id="editMemberId" name="member_id">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Position <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editPosition" name="position" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="editEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="editIsActive" name="is_active">
                            <label class="form-check-label" for="editIsActive">
                                Active Member
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadMembers();
    
    // Handle position select change
    document.querySelector('[name="position"]').addEventListener('change', function() {
        const customInput = document.querySelector('[name="custom_position"]');
        if (this.value === 'custom') {
            customInput.classList.remove('d-none');
            customInput.required = true;
        } else {
            customInput.classList.add('d-none');
            customInput.required = false;
            customInput.value = '';
        }
    });
    
    // Handle add member form
    document.getElementById('addMemberForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        // Use custom position if selected
        if (formData.get('position') === 'custom') {
            formData.set('position', formData.get('custom_position'));
        }
        
        const data = Object.fromEntries(formData);
        
        fetch('{{ route("voting.api.members.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('addMemberModal')).hide();
                showAlert('Member added successfully!', 'success');
                loadMembers();
                this.reset();
            } else {
                showAlert('Failed to add member: ' + (result.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            showAlert('Error adding member: ' + error.message, 'danger');
        });
    });
    
    // Handle edit member form
    document.getElementById('editMemberForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const memberId = formData.get('member_id');
        const data = Object.fromEntries(formData);
        data.is_active = document.getElementById('editIsActive').checked;
        
        fetch(`/admin/voting/api/members/${memberId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('editMemberModal')).hide();
                showAlert('Member updated successfully!', 'success');
                loadMembers();
            } else {
                showAlert('Failed to update member: ' + (result.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            showAlert('Error updating member: ' + error.message, 'danger');
        });
    });
});

function loadMembers() {
    fetch('{{ route("voting.api.members.get") }}')
        .then(response => response.json())
        .then(members => {
            const tbody = document.querySelector('#membersTable tbody');
            tbody.innerHTML = '';
            
            if (members && members.length > 0) {
                members.forEach(member => {
                    const row = `
                        <tr>
                            <td>${member.name || 'N/A'}</td>
                            <td>${member.position || 'N/A'}</td>
                            <td>${member.email || 'N/A'}</td>
                            <td>
                                <span class="badge ${member.is_active ? 'bg-success' : 'bg-secondary'}">
                                    ${member.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editMember(${member.id})">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deactivateMember(${member.id})" 
                                        ${!member.is_active ? 'disabled' : ''}>
                                    <i class="fas fa-user-times"></i> Deactivate
                                </button>
                            </td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No members found</td></tr>';
            }
        })
        .catch(error => {
            document.querySelector('#membersTable tbody').innerHTML = 
                '<tr><td colspan="5" class="text-center text-danger">Error loading members</td></tr>';
            console.error('Error loading members:', error);
        });
}

function editMember(memberId) {
    fetch('{{ route("voting.api.members.get") }}')
        .then(response => response.json())
        .then(members => {
            const member = members.find(m => m.id === memberId);
            if (member) {
                document.getElementById('editMemberId').value = member.id;
                document.getElementById('editName').value = member.name;
                document.getElementById('editPosition').value = member.position;
                document.getElementById('editEmail').value = member.email;
                document.getElementById('editIsActive').checked = member.is_active;
                
                new bootstrap.Modal(document.getElementById('editMemberModal')).show();
            }
        });
}

function deactivateMember(memberId) {
    if (confirm('Are you sure you want to deactivate this member? They will no longer receive voting emails.')) {
        fetch(`/admin/voting/api/members/${memberId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showAlert('Member deactivated successfully!', 'success');
                loadMembers();
            } else {
                showAlert('Failed to deactivate member: ' + (result.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            showAlert('Error deactivating member: ' + error.message, 'danger');
        });
    }
}

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const container = document.querySelector('.container-fluid');
    container.insertAdjacentHTML('afterbegin', alertHtml);
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        const alert = container.querySelector('.alert');
        if (alert) {
            bootstrap.Alert.getInstance(alert)?.close();
        }
    }, 5000);
}
</script>
@endsection
