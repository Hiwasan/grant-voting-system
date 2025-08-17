from flask import Flask, request, jsonify, render_template_string
from flask_sqlalchemy import SQLAlchemy
from flask_mail import Mail, Message
from datetime import datetime
import secrets
import hashlib
from enum import Enum

app = Flask(__name__)

# Configuration
app.config['SECRET_KEY'] = 'your-secret-key-here'
app.config['SQLALCHEMY_DATABASE_URI'] = 'sqlite:///voting_system.db'
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False

# Email configuration (configure according to your email service)
app.config['MAIL_SERVER'] = 'smtp.gmail.com'
app.config['MAIL_PORT'] = 587
app.config['MAIL_USE_TLS'] = True
app.config['MAIL_USERNAME'] = 'your-email@gmail.com'
app.config['MAIL_PASSWORD'] = 'your-email-password'

db = SQLAlchemy(app)
mail = Mail(app)

class GrantType(Enum):
    STSM = "STSM"
    DISSEMINATION_CONFERENCE = "Dissemination Conference Grant"
    ITC_CONFERENCE = "ITC Conference Grant"
    YRI_CONFERENCE = "Young Researchers and Innovators (YRI) Conference Grants"

class VoteType(Enum):
    ACCEPT = "Accept"
    REJECT = "Reject"

# Database Models
class VotingMember(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    name = db.Column(db.String(100), nullable=False)
    position = db.Column(db.String(100), nullable=False)
    email = db.Column(db.String(120), nullable=False, unique=True)
    is_active = db.Column(db.Boolean, default=True)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

class GrantApplication(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    reference_code = db.Column(db.String(20), nullable=False, unique=True)
    submitter_name = db.Column(db.String(100), nullable=False)
    candidate_full_name = db.Column(db.String(100), nullable=False)
    grant_type = db.Column(db.Enum(GrantType), nullable=False)
    date = db.Column(db.Date, nullable=False)
    place = db.Column(db.String(200), nullable=False)
    amount_requested = db.Column(db.Float, nullable=False)
    currency = db.Column(db.String(10), default='EUR')
    description = db.Column(db.Text)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    voting_deadline = db.Column(db.DateTime, nullable=False)
    is_active = db.Column(db.Boolean, default=True)
    
    votes = db.relationship('Vote', backref='application', lazy=True)

class Vote(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    application_id = db.Column(db.Integer, db.ForeignKey('grant_application.id'), nullable=False)
    voter_id = db.Column(db.Integer, db.ForeignKey('voting_member.id'), nullable=False)
    vote_type = db.Column(db.Enum(VoteType), nullable=False)
    rejection_reason = db.Column(db.Text)
    token = db.Column(db.String(64), nullable=False, unique=True)
    voted_at = db.Column(db.DateTime, default=datetime.utcnow)
    
    voter = db.relationship('VotingMember', backref='votes')

class Comment(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    application_id = db.Column(db.Integer, db.ForeignKey('grant_application.id'), nullable=False)
    voter_id = db.Column(db.Integer, db.ForeignKey('voting_member.id'), nullable=False)
    parent_comment_id = db.Column(db.Integer, db.ForeignKey('comment.id'))
    content = db.Column(db.Text, nullable=False)
    is_supportive = db.Column(db.Boolean)  # True for support, False for opposition
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    
    voter = db.relationship('VotingMember', backref='comments')
    application = db.relationship('GrantApplication', backref='comments')
    replies = db.relationship('Comment', backref=db.backref('parent', remote_side=[id]))

# Utility Functions
def generate_voting_token():
    return secrets.token_urlsafe(32)

def send_voting_notification(application_id):
    """Send voting notification emails to all active members"""
    application = GrantApplication.query.get(application_id)
    members = VotingMember.query.filter_by(is_active=True).all()
    
    for member in members:
        token = generate_voting_token()
        vote = Vote(
            application_id=application_id,
            voter_id=member.id,
            vote_type=VoteType.ACCEPT,  # Placeholder, will be updated when voted
            token=token
        )
        db.session.add(vote)
        
        # Send email
        voting_url = f"http://your-domain.com/vote/{token}"
        
        msg = Message(
            subject=f'Grant Voting Required - {application.reference_code}',
            sender=app.config['MAIL_USERNAME'],
            recipients=[member.email]
        )
        
        msg.html = f"""
        <h2>Grant Application Voting Required</h2>
        <p>Dear {member.name},</p>
        
        <p>A new grant application requires your vote:</p>
        
        <div style="background: #f5f5f5; padding: 15px; margin: 15px 0;">
            <strong>Reference:</strong> {application.reference_code}<br>
            <strong>Candidate:</strong> {application.candidate_full_name}<br>
            <strong>Grant Type:</strong> {application.grant_type.value}<br>
            <strong>Date:</strong> {application.date}<br>
            <strong>Place:</strong> {application.place}<br>
            <strong>Amount:</strong> {application.amount_requested} {application.currency}
        </div>
        
        <p><a href="{voting_url}" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">VOTE NOW</a></p>
        
        <p>Voting deadline: {application.voting_deadline.strftime('%Y-%m-%d %H:%M')}</p>
        
        <p>Best regards,<br>Grant Management System</p>
        """
        
        mail.send(msg)
    
    db.session.commit()

# API Routes
@app.route('/')
def home():
    return '''
    <html>
    <head>
        <title>Grant Voting System</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; }
            h1 { color: #005a9c; }
            ul { line-height: 1.8; }
        </style>
    </head>
    <body>
        <h1>Grant Voting System</h1>
        <p>✅ Server is running!</p>
        <ul>
            <li><a href="/api/members">View Voting Members (JSON)</a></li>
            <li><a href="/health">Health Check (JSON)</a></li>
        </ul>
        <p>Use API routes for integration. See <code>documentation/</code> for details.</p>
    </body>
    </html>
    '''

@app.route('/health')
def health():
    """Health check endpoint for monitoring"""
    return jsonify({
        'status': 'healthy',
        'database': 'connected',
        'timestamp': datetime.utcnow().isoformat()
    })

@app.route('/api/members', methods=['GET'])
def get_members():
    """Get all voting members"""
    members = VotingMember.query.all()
    return jsonify([{
        'id': m.id,
        'name': m.name,
        'position': m.position,
        'email': m.email,
        'is_active': m.is_active
    } for m in members])

@app.route('/api/members', methods=['POST'])
def add_member():
    """Add new voting member"""
    data = request.json
    
    member = VotingMember(
        name=data['name'],
        position=data['position'],
        email=data['email']
    )
    
    db.session.add(member)
    db.session.commit()
    
    return jsonify({'message': 'Member added successfully', 'id': member.id})

@app.route('/api/members/<int:member_id>', methods=['PUT'])
def update_member(member_id):
    """Update voting member"""
    member = VotingMember.query.get_or_404(member_id)
    data = request.json
    
    member.name = data.get('name', member.name)
    member.position = data.get('position', member.position)
    member.email = data.get('email', member.email)
    member.is_active = data.get('is_active', member.is_active)
    
    db.session.commit()
    
    return jsonify({'message': 'Member updated successfully'})

@app.route('/api/members/<int:member_id>', methods=['DELETE'])
def deactivate_member(member_id):
    """Deactivate voting member"""
    member = VotingMember.query.get_or_404(member_id)
    member.is_active = False
    db.session.commit()
    
    return jsonify({'message': 'Member deactivated successfully'})

@app.route('/api/applications', methods=['POST'])
def create_application():
    """Create new grant application"""
    data = request.json
    
    # Generate unique reference code
    ref_code = f"CA{secrets.randbelow(999999):06d}"
    
    application = GrantApplication(
        reference_code=ref_code,
        submitter_name=data['submitter_name'],
        candidate_full_name=data['candidate_full_name'],
        grant_type=GrantType(data['grant_type']),
        date=datetime.strptime(data['date'], '%Y-%m-%d').date(),
        place=data['place'],
        amount_requested=float(data['amount_requested']),
        currency=data.get('currency', 'EUR'),
        description=data.get('description', ''),
        voting_deadline=datetime.strptime(data['voting_deadline'], '%Y-%m-%d %H:%M')
    )
    
    db.session.add(application)
    db.session.commit()
    
    # Send voting notifications
    send_voting_notification(application.id)
    
    return jsonify({
        'message': 'Application created successfully',
        'reference_code': ref_code,
        'id': application.id
    })

@app.route('/api/applications/<int:app_id>', methods=['GET'])
def get_application(app_id):
    """Get application details"""
    app_obj = GrantApplication.query.get_or_404(app_id)
    
    votes = Vote.query.filter_by(application_id=app_id).all()
    vote_summary = {
        'accept': sum(1 for v in votes if v.vote_type == VoteType.ACCEPT),
        'reject': sum(1 for v in votes if v.vote_type == VoteType.REJECT),
        'total_voters': VotingMember.query.filter_by(is_active=True).count(),
        'voted': len(votes)
    }
    
    return jsonify({
        'id': app_obj.id,
        'reference_code': app_obj.reference_code,
        'submitter_name': app_obj.submitter_name,
        'candidate_full_name': app_obj.candidate_full_name,
        'grant_type': app_obj.grant_type.value,
        'date': app_obj.date.isoformat(),
        'place': app_obj.place,
        'amount_requested': app_obj.amount_requested,
        'currency': app_obj.currency,
        'description': app_obj.description,
        'voting_deadline': app_obj.voting_deadline.isoformat(),
        'vote_summary': vote_summary
    })

@app.route('/vote/<token>')
def vote_page(token):
    """Display voting page"""
    vote = Vote.query.filter_by(token=token).first_or_404()
    application = vote.application
    
    # Check if voting is still active
    if datetime.utcnow() > application.voting_deadline:
        return "Voting period has ended", 400
    
    template = """
    <!DOCTYPE html>
    <html>
    <head>
        <title>Grant Voting - {{ application.reference_code }}</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
            .header { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
            .form-group { margin-bottom: 15px; }
            .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
            .form-group input, .form-group textarea, .form-group select { 
                width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; 
            }
            .vote-buttons { display: flex; gap: 10px; margin: 20px 0; }
            .vote-btn { padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
            .accept-btn { background: #28a745; color: white; }
            .reject-btn { background: #dc3545; color: white; }
            .rejection-reason { display: none; margin-top: 15px; }
            .comments-section { margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; }
            .comment { background: white; padding: 15px; margin-bottom: 10px; border-radius: 5px; border-left: 4px solid #007bff; }
            .comment.support { border-left-color: #28a745; }
            .comment.oppose { border-left-color: #dc3545; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Grant Voting: {{ application.reference_code }}</h1>
            <p><strong>Voter:</strong> {{ voter.name }} ({{ voter.position }})</p>
        </div>
        
        <div class="application-details">
            <h2>Application Details</h2>
            <p><strong>Candidate:</strong> {{ application.candidate_full_name }}</p>
            <p><strong>Grant Type:</strong> {{ application.grant_type.value }}</p>
            <p><strong>Date:</strong> {{ application.date }}</p>
            <p><strong>Place:</strong> {{ application.place }}</p>
            <p><strong>Amount Requested:</strong> {{ application.amount_requested }} {{ application.currency }}</p>
            {% if application.description %}
            <p><strong>Description:</strong> {{ application.description }}</p>
            {% endif %}
        </div>
        
        <form id="voteForm" action="/api/vote/{{ token }}" method="POST">
            <h2>Your Vote</h2>
            <div class="vote-buttons">
                <button type="button" class="vote-btn accept-btn" onclick="selectVote('accept')">Accept</button>
                <button type="button" class="vote-btn reject-btn" onclick="selectVote('reject')">Reject</button>
            </div>
            
            <input type="hidden" id="voteType" name="vote_type" required>
            
            <div id="rejectionReason" class="rejection-reason">
                <div class="form-group">
                    <label for="reason">Rejection Reason (Required for rejection):</label>
                    <textarea id="reason" name="rejection_reason" rows="4" placeholder="Please provide detailed justification for rejection..."></textarea>
                </div>
            </div>
            
            <button type="submit" style="background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer;">Submit Vote</button>
        </form>
        
        <div class="comments-section">
            <h2>Discussion</h2>
            <form id="commentForm" action="/api/comment/{{ token }}" method="POST">
                <div class="form-group">
                    <label for="comment">Add Comment:</label>
                    <textarea id="comment" name="content" rows="3" placeholder="Share your thoughts or questions..."></textarea>
                </div>
                <div class="form-group">
                    <label>
                        <input type="radio" name="is_supportive" value="true"> Support Application
                    </label>
                    <label>
                        <input type="radio" name="is_supportive" value="false"> Oppose Application
                    </label>
                    <label>
                        <input type="radio" name="is_supportive" value="" checked> Neutral Comment
                    </label>
                </div>
                <button type="submit">Add Comment</button>
            </form>
            
            <div id="comments">
                <!-- Comments will be loaded here -->
            </div>
        </div>
        
        <script>
            function selectVote(type) {
                document.getElementById('voteType').value = type;
                document.querySelectorAll('.vote-btn').forEach(btn => btn.style.opacity = '0.5');
                document.querySelector('.' + type + '-btn').style.opacity = '1';
                
                if (type === 'reject') {
                    document.getElementById('rejectionReason').style.display = 'block';
                    document.getElementById('reason').required = true;
                } else {
                    document.getElementById('rejectionReason').style.display = 'none';
                    document.getElementById('reason').required = false;
                }
            }
            
            // Load comments
            fetch('/api/comments/{{ application.id }}')
                .then(response => response.json())
                .then(comments => {
                    const commentsDiv = document.getElementById('comments');
                    comments.forEach(comment => {
                        const div = document.createElement('div');
                        div.className = 'comment' + (comment.is_supportive === true ? ' support' : comment.is_supportive === false ? ' oppose' : '');
                        div.innerHTML = `
                            <strong>${comment.voter_name} (${comment.voter_position})</strong>
                            <small style="float: right;">${new Date(comment.created_at).toLocaleString()}</small>
                            <p>${comment.content}</p>
                        `;
                        commentsDiv.appendChild(div);
                    });
                });
        </script>
    </body>
    </html>
    """
    
    return render_template_string(template, 
                                 application=application, 
                                 voter=vote.voter, 
                                 token=token)

@app.route('/api/vote/<token>', methods=['POST'])
def submit_vote(token):
    """Submit vote"""
    vote = Vote.query.filter_by(token=token).first_or_404()
    
    # Check if voting is still active
    if datetime.utcnow() > vote.application.voting_deadline:
        return jsonify({'error': 'Voting period has ended'}), 400
    
    data = request.form
    vote_type = VoteType(data['vote_type'].upper())
    
    vote.vote_type = vote_type
    vote.voted_at = datetime.utcnow()
    
    if vote_type == VoteType.REJECT:
        if not data.get('rejection_reason'):
            return jsonify({'error': 'Rejection reason is required'}), 400
        vote.rejection_reason = data['rejection_reason']
    
    db.session.commit()
    
    return jsonify({'message': 'Vote submitted successfully'})

@app.route('/api/comment/<token>', methods=['POST'])
def add_comment(token):
    """Add comment to application"""
    vote = Vote.query.filter_by(token=token).first_or_404()
    data = request.form
    
    comment = Comment(
        application_id=vote.application_id,
        voter_id=vote.voter_id,
        content=data['content'],
        is_supportive=None if data['is_supportive'] == '' else data['is_supportive'] == 'true'
    )
    
    db.session.add(comment)
    db.session.commit()
    
    return jsonify({'message': 'Comment added successfully'})

@app.route('/api/comments/<int:app_id>')
def get_comments(app_id):
    """Get comments for application"""
    comments = db.session.query(Comment, VotingMember).join(VotingMember).filter(
        Comment.application_id == app_id
    ).order_by(Comment.created_at.desc()).all()
    
    return jsonify([{
        'id': comment.id,
        'content': comment.content,
        'is_supportive': comment.is_supportive,
        'voter_name': voter.name,
        'voter_position': voter.position,
        'created_at': comment.created_at.isoformat()
    } for comment, voter in comments])

@app.route('/api/results/<int:app_id>')
def get_voting_results(app_id):
    """Get voting results"""
    application = GrantApplication.query.get_or_404(app_id)
    
    votes = Vote.query.filter_by(application_id=app_id).all()
    
    results = {
        'application': {
            'reference_code': application.reference_code,
            'candidate_full_name': application.candidate_full_name,
            'grant_type': application.grant_type.value
        },
        'summary': {
            'accept': sum(1 for v in votes if v.vote_type == VoteType.ACCEPT),
            'reject': sum(1 for v in votes if v.vote_type == VoteType.REJECT),
            'total_voters': VotingMember.query.filter_by(is_active=True).count(),
            'voted': len(votes)
        },
        'votes': [{
            'voter_name': v.voter.name,
            'voter_position': v.voter.position,
            'vote': v.vote_type.value,
            'rejection_reason': v.rejection_reason,
            'voted_at': v.voted_at.isoformat() if v.voted_at else None
        } for v in votes],
        'rejection_reasons': [v.rejection_reason for v in votes if v.rejection_reason]
    }
    
    return jsonify(results)

# Initialize database
# @app.before_first_request
# def create_tables():
#     db.create_all()
    
#     # Add default voting members if none exist
#     if VotingMember.query.count() == 0:
#         default_members = [
#             {'name': 'Action Chair', 'position': 'Action Chair', 'email': 'chair@example.com'},
#             {'name': 'Action Vice Chair', 'position': 'Action Vice Chair', 'email': 'vicechair@example.com'},
#             {'name': 'Grant Holder Scientific Representative', 'position': 'Grant Holder Scientific Representative', 'email': 'scientific@example.com'},
#             {'name': 'Science Communication Coordinator', 'position': 'Science Communication Coordinator', 'email': 'communication@example.com'},
#             {'name': 'Grant Awarding Coordinator', 'position': 'Grant Awarding Coordinator', 'email': 'awarding@example.com'},
#             {'name': 'WG1 Leader', 'position': 'WG1 Leader', 'email': 'wg1leader@example.com'},
#             {'name': 'WG1 Vice Leader', 'position': 'WG1 Vice Leader', 'email': 'wg1vice@example.com'},
#             {'name': 'WG2 Leader', 'position': 'WG2 Leader', 'email': 'wg2leader@example.com'},
#             {'name': 'WG2 Vice Leader', 'position': 'WG2 Vice Leader', 'email': 'wg2vice@example.com'},
#             {'name': 'WG3 Leader', 'position': 'WG3 Leader', 'email': 'wg3leader@example.com'},
#             {'name': 'WG3 Vice Leader', 'position': 'WG3 Vice Leader', 'email': 'wg3vice@example.com'},
#             {'name': 'WG4 Leader', 'position': 'WG4 Leader', 'email': 'wg4leader@example.com'},
#             {'name': 'WG4 Vice Leader', 'position': 'WG4 Vice Leader', 'email': 'wg4vice@example.com'},
#             {'name': 'WG5 Leader', 'position': 'WG5 Leader', 'email': 'wg5leader@example.com'},
#             {'name': 'WG5 Vice Leader', 'position': 'WG5 Vice Leader', 'email': 'wg5vice@example.com'},
#         ]
        
#         for member_data in default_members:
#             member = VotingMember(**member_data)
#             db.session.add(member)
        
#         db.session.commit()

if __name__ == '__main__':
    with app.app_context():
        # Create all database tables
        db.create_all()
        
        # Add default voting members if none exist
        if VotingMember.query.count() == 0:
            default_members = [
                {
                    'name': 'Prof Katrin SCHLUND',
                    'position': 'Action Chair',
                    'email': 'katrin.schlund@slavistik.uni-halle.de'
                },
                {
                    'name': 'Prof Vladimir KARABALIĆ',
                    'position': 'Action Vice Chair',
                    'email': 'vkarabalic@ffos.hr'
                },
                {
                    'name': 'Prof Vladimir KARABALIĆ',
                    'position': 'Grant Holder Scientific Representative',
                    'email': 'vkarabalic@ffos.hr'
                },
                {
                    'name': 'Dr Gokhan OZKAN',
                    'position': 'Science Communication Coordinator',
                    'email': 'Gkhnozkan57@gmail.com'
                },
                {
                    'name': 'Prof Hana BERGEROVA',
                    'position': 'Grant Awarding Coordinator',
                    'email': 'Hana.Bergerova@ujep.cz'
                },
                {
                    'name': 'Prof Carmen MELLADO BLANCO',
                    'position': 'WG1 Leader',
                    'email': 'c.mellado@usc.es'
                },
                {
                    'name': 'Roberta Rada',
                    'position': 'WG1 Vice Leader',
                    'email': 'rada.roberta@gmail.com'
                },
                {
                    'name': 'Dr Nikolche MICKOSKI',
                    'position': 'WG2 Leader',
                    'email': 'nmickoski@manu.edu.mk'
                },
                {
                    'name': 'Max Silbersztein',
                    'position': 'WG2 Vice Leader',
                    'email': 'max.silberztein@gmail.com'
                },
                {
                    'name': 'Prof Vladimir KARABALIĆ',
                    'position': 'WG3 Leader',
                    'email': 'vkarabalic@ffos.hr'
                },
                {
                    'name': 'Monika Hornacek-Banasova',
                    'position': 'WG3 Vice Leader',
                    'email': 'monika.hornacek.banasova@ucm.sk'
                },
                {
                    'name': 'Dr Tamas KISPAL',
                    'position': 'WG4 Leader',
                    'email': 'tamas.kispal@phil.uni-goettingen.de'
                },
                {
                    'name': 'Çiler Hatipoğlu',
                    'position': 'WG4 Vice Leader',
                    'email': 'ciler.hatipoglu@gmail.com'
                },
                {
                    'name': 'Dr. Hiwa Asadpour',
                    'position': 'WG5 Leader',
                    'email': 'asadpourhiwa@gmail.com'
                },
                {
                    'name': 'Pedro Ivorra Ordines',
                    'position': 'WG5 Vice Leader',
                    'email': 'pivorra@unizar.es'
                }
            ]
            
            for member_data in default_members:
                member = VotingMember(**member_data)
                db.session.add(member)
            
            db.session.commit()
            print("✅ Default voting members added to database.")
        
        print("✅ Database initialized.")

    # Run the Flask app
    app.run(debug=True)