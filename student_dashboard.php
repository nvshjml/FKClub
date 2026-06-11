<?php
session_start();
require 'db_connect.php';
require 'session_timeout.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);

$stmt1 = $conn->prepare("SELECT total_point FROM `USER` WHERE user_id = ?");
$stmt1->bind_param("s", $user_id); $stmt1->execute();
$points_result = $stmt1->get_result()->fetch_assoc();
$total_points = $points_result ? $points_result['total_point'] : 0;

$stmt2 = $conn->prepare("SELECT COUNT(*) AS total FROM CLUB_MEMBERSHIP WHERE user_id = ?");
$stmt2->bind_param("s", $user_id); $stmt2->execute();
$total_clubs = $stmt2->get_result()->fetch_assoc()['total'];

$stmt3 = $conn->prepare("SELECT COUNT(*) AS total FROM EVENT_REGISTRATION WHERE user_id = ?");
$stmt3->bind_param("s", $user_id); $stmt3->execute();
$total_events = $stmt3->get_result()->fetch_assoc()['total'];

// UPDATED QUERY: Removed the date filter so it fetches BOTH past and upcoming events, ordered by newest first.
$events_sql = "SELECT e.*, c.club_name FROM EVENT e JOIN CLUB c ON e.club_id = c.club_id ORDER BY e.date DESC";
$events_result = $conn->query($events_sql);

$clubs_sql = "SELECT c.club_id, c.club_name, u.name AS president_name FROM CLUB c LEFT JOIN COMMITTEE com ON c.club_id = com.club_id AND com.position = 'President' LEFT JOIN `USER` u ON com.user_id = u.user_id WHERE c.isActive = 1";
$clubs_result = $conn->query($clubs_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css"> 
    <style>
        /* THE LAYOUT CSS (Safely included!) */
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { display: flex; background: #e2e8f0; min-height: 100vh; color: #333; }
        .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; max-width: 1200px; }
        
        .welcome-card { background-color: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 6px solid #38a169; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 13px; color: #718096; text-transform: uppercase; }
        .stat-card .number { font-size: 2.5rem; font-weight: 700; color: #2d3748; margin-top: 10px; }
        .section-title { font-size: 22px; color: #1a202c; margin-bottom: 20px; border-bottom: 2px solid #cbd5e0; padding-bottom: 10px; }
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; margin-bottom: 50px; }
        .item-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: 0.3s; }
        .item-image { width: 100%; height: 160px; object-fit: cover; background: #edf2f7; }
        .item-content { padding: 20px; }
        
        /* Updated Event Title layout for badges */
        .event-title-wrapper { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 10px; gap: 10px; }
        .item-title { font-size: 18px; font-weight: bold; color: #2d3748; }
        .badge-upcoming { background: #c6f6d5; color: #22543d; padding: 4px 8px; border-radius: 20px; font-size: 11px; font-weight: bold; white-space: nowrap; }
        .badge-past { background: #e2e8f0; color: #4a5568; padding: 4px 8px; border-radius: 20px; font-size: 11px; font-weight: bold; white-space: nowrap; }
        
        .item-detail { font-size: 14px; color: #718096; margin-bottom: 5px; }
        
        .btn-action { display: block; width: 100%; text-align: center; padding: 10px; margin-top: 15px; background: #3182ce; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration:none; transition: 0.2s; }
        .btn-action:hover { background: #2b6cb0; }
        
        /* Expandable details section */
        .event-extra-details { display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #edf2f7; font-size: 13px; color: #4a5568; animation: fadeIn 0.3s ease-in-out; }
        .event-extra-details p { margin-bottom: 8px; line-height: 1.5; }
        .event-extra-details strong { color: #2d3748; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="welcome-card">
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>! 🎓</h2>
            <p style="color: #718096;">This is your student portal. Track your points, manage your clubs, and view campus events.</p>
        </div>
        <div class="stats-grid">
            <div class="stat-card" style="border-bottom: 4px solid #3182ce;">
                <h3>Total Points</h3><div class="number"><?php echo $total_points; ?></div>
            </div>
            <div class="stat-card" style="border-bottom: 4px solid #38a169;">
                <h3>Clubs Joined</h3><div class="number"><?php echo $total_clubs; ?> / 3</div>
            </div>
            <div class="stat-card" style="border-bottom: 4px solid #805ad5;">
                <h3>Events Registered</h3><div class="number"><?php echo $total_events; ?></div>
            </div>
        </div>
        
        <h2 class="section-title">📅 Campus Events</h2>
        <div class="grid-container">
            <?php if ($events_result && $events_result->num_rows > 0): ?>
                <?php while($event = $events_result->fetch_assoc()): 
                    // Calculate if the event is in the past
                    $is_past = strtotime($event['date']) < strtotime(date('Y-m-d'));
                ?>
                    <div class="item-card">
                        <img src="https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=500&q=80" class="item-image" alt="Event">
                        <div class="item-content">
                            
                            <div class="event-title-wrapper">
                                <div class="item-title"><?php echo htmlspecialchars($event['event_name']); ?></div>
                                <?php if ($is_past): ?>
                                    <span class="badge-past">Past</span>
                                <?php else: ?>
                                    <span class="badge-upcoming">Upcoming</span>
                                <?php endif; ?>
                            </div>

                            <div class="item-detail">🏛️ <strong><?php echo htmlspecialchars($event['club_name']); ?></strong></div>
                            <div class="item-detail">📅 <?php echo date("d M Y", strtotime($event['date'])); ?></div>
                            <div class="item-detail">⏰ <?php echo date("h:i A", strtotime($event['time'])); ?></div>
                            <div class="item-detail">📍 <?php echo htmlspecialchars($event['venue']); ?></div>
                            
                            <div id="details_<?php echo $event['event_id']; ?>" class="event-extra-details">
                                <p><strong>Description:</strong><br> <?php echo htmlspecialchars($event['description'] ?? 'No description provided.'); ?></p>
                                <p><strong>Capacity:</strong> <?php echo $event['max_cap'] ? htmlspecialchars($event['max_cap']) . ' students max' : 'Unlimited'; ?></p>
                            </div>

                            <button class="btn-action" onclick="toggleDetails('details_<?php echo $event['event_id']; ?>', this)">See More Detail</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No events found.</p>
            <?php endif; ?>
        </div>

        <h2 class="section-title">🏆 Available Clubs</h2>
        <div class="grid-container">
            <?php if ($clubs_result && $clubs_result->num_rows > 0): ?>
                <?php while($club = $clubs_result->fetch_assoc()): ?>
                    <div class="item-card">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($club['club_name']); ?>&background=random&size=300" class="item-image" alt="Club Logo">
                        <div class="item-content">
                            <div class="item-title"><?php echo htmlspecialchars($club['club_name']); ?></div>
                            <div class="item-detail">👤 <strong>President:</strong> <?php echo $club['president_name'] ? htmlspecialchars($club['president_name']) : 'TBD'; ?></div>
                            <a href="student_club_details.php?club_id=<?php echo $club['club_id']; ?>" class="btn-action">ℹ️ View Details</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No clubs available right now.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleDetails(detailId, buttonElement) {
            var detailsDiv = document.getElementById(detailId);
            
            if (detailsDiv.style.display === "none" || detailsDiv.style.display === "") {
                detailsDiv.style.display = "block";
                buttonElement.innerText = "Hide Details";
                buttonElement.style.background = "#e2e8f0";
                buttonElement.style.color = "#4a5568";
            } else {
                detailsDiv.style.display = "none";
                buttonElement.innerText = "See More Detail";
                buttonElement.style.background = "#3182ce";
                buttonElement.style.color = "white";
            }
        }
    </script>
</body>
</html>