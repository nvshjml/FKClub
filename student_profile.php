<?php
session_start();
require 'db_connect.php';

// SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'Admin') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$show_form = false; 

// ---------------------------------------------------------
// 1. HANDLE PROFILE UPDATE
// ---------------------------------------------------------
if (isset($_POST['update_btn'])) {
    $new_phone = $_POST['phone'];
    $new_password = $_POST['password'];
    
    if (!empty($new_password)) {
        $stmt = $conn->prepare("UPDATE `USER` SET phone = ?, pass_hash = ? WHERE user_id = ?");
        $stmt->bind_param("sss", $new_phone, $new_password, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE `USER` SET phone = ? WHERE user_id = ?");
        $stmt->bind_param("ss", $new_phone, $user_id);
    }
    
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>✅ Profile updated successfully!</div>";
        $show_form = false; // Hide the form on success
    } else {
        $message = "<div class='alert alert-error'>❌ Error updating profile.</div>";
        $show_form = true; // Keep form open if there is an error
    }
}

// ---------------------------------------------------------
// 2. FETCH CURRENT PROFILE DATA
// ---------------------------------------------------------
$stmt = $conn->prepare("SELECT * FROM `USER` WHERE user_id = ?");
$stmt->bind_param("s", $user_id); 
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { 
            display: flex; 
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('https://images.unsplash.com/photo-1541339907198-e08756dedf3f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') no-repeat center center/cover; 
            min-height: 100vh; 
            backdrop-filter: blur(5px); 
            background-attachment: fixed; 
        }
        
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; display: flex; flex-direction: column; align-items: center; }
        .profile-container { width: 100%; max-width: 600px; display: flex; flex-direction: column; gap: 20px; }
        
        .card { background: rgba(255, 255, 255, 0.95); padding: 35px; border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); width: 100%; }
        .card h2 { margin-bottom: 20px; color: #1a202c; font-size: 22px; display: flex; align-items: center; gap: 10px;}
        
        .info-list { list-style: none; margin-bottom: 25px; }
        .info-list li { display: flex; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid #edf2f7; font-size: 15px; }
        .info-list li:last-child { border-bottom: none; }
        .info-label { color: #4a5568; }
        .info-value { font-weight: 600; color: #1a202c; text-align: right; }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #4a5568; font-weight: 600; font-size: 14px; }
        input { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; outline: none; transition: 0.3s; }
        input:focus { border-color: #3182ce; box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.2); }
        
        .btn-toggle { background: #edf2f7; color: #4a5568; border: none; padding: 12px 20px; border-radius: 8px; width: 100%; font-weight: bold; cursor: pointer; transition: 0.2s; font-size: 15px; }
        .btn-toggle:hover { background: #e2e8f0; color: #2d3748; }
        
        .btn-save { background: #3182ce; color: white; border: none; padding: 12px 20px; border-radius: 8px; width: 100%; font-weight: bold; cursor: pointer; font-size: 15px; margin-bottom: 10px; transition: 0.2s;}
        .btn-save:hover { background: #2b6cb0; }
        
        .btn-cancel { background: white; color: #e53e3e; border: 2px solid #fed7d7; padding: 10px 20px; border-radius: 8px; width: 100%; font-weight: bold; cursor: pointer; font-size: 15px; transition: 0.2s;}
        .btn-cancel:hover { background: #fff5f5; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; text-align: center; }
        .alert-success { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background-color: #fed7d7; color: #822727; border: 1px solid #feb2b2; }
        
        #editCard { display: <?php echo $show_form ? 'block' : 'none'; ?>; animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="profile-container">
            <?php echo $message; ?>
            
            <div class="card" id="viewCard">
                <h2>👤 My Profile</h2>
                
                <ul class="info-list">
                    <li><span class="info-label">Name</span><span class="info-value"><?php echo htmlspecialchars($user_data['name']); ?></span></li>
                    <li><span class="info-label">Matrix ID</span><span class="info-value"><?php echo htmlspecialchars($user_data['user_id']); ?></span></li>
                    <li><span class="info-label">Email</span><span class="info-value"><?php echo htmlspecialchars($user_data['email']); ?></span></li>
                    <li><span class="info-label">Phone</span><span class="info-value"><?php echo htmlspecialchars($user_data['phone'] ?? '-'); ?></span></li>
                    
                    <?php if ($user_data['role'] == 'Student'): ?>
                        <li><span class="info-label">Total Points</span><span class="info-value" style="color: #38a169; font-size: 18px;"><?php echo htmlspecialchars($user_data['total_point']); ?></span></li>
                    <?php endif; ?>
                </ul>

                <button class="btn-toggle" onclick="toggleEdit()">✏️ Edit Profile</button>
            </div>

            <div class="card" id="editCard">
                <h2>⚙️ Update Information</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Update Phone</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="password" placeholder="(Leave blank to keep current)">
                    </div>
                    
                    <button type="submit" name="update_btn" class="btn-save">Save Changes</button>
                    <button type="button" class="btn-cancel" onclick="toggleEdit()">Cancel</button>
                </form>
            </div>

        </div>
    </div>

    <script>
        function toggleEdit() {
            var editCard = document.getElementById("editCard");
            var editBtn = document.querySelector(".btn-toggle");
            
            if (editCard.style.display === "none" || editCard.style.display === "") {
                editCard.style.display = "block";
                editBtn.style.display = "none"; // Hides the "Edit" button on the first card while editing
                // Scroll smoothly to the edit form
                editCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                editCard.style.display = "none";
                editBtn.style.display = "block"; // Brings the "Edit" button back
            }
        }
    </script>
</body>
</html>