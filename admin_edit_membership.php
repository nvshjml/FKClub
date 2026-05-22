<?php
session_start();
require 'db_connect.php';

$user_id = $_GET['user_id'];
$club_id = $_GET['club_id'];

if (isset($_POST['update_membership'])) {
    $new_position = $_POST['position'];
    $stmt = $conn->prepare("UPDATE committee SET position = ? WHERE user_id = ? AND club_id = ?");
    $stmt->bind_param("ssi", $new_position, $user_id, $club_id);
    $stmt->execute();
    header("Location: admin_view_club_members.php?club_id=$club_id");
    exit();
}

// Fetch current data
$member = $conn->query("SELECT position FROM committee WHERE user_id = '$user_id' AND club_id = $club_id")->fetch_assoc();
?>

<div class="card">
    <h2>Edit Member Position</h2>
    <form method="POST">
        <label>New Position/Role:</label>
        <select name="position">
            <option value="President" <?php if($member['position']=='President') echo 'selected'; ?>>President</option>
            <option value="Secretary" <?php if($member['position']=='Secretary') echo 'selected'; ?>>Secretary</option>
            <option value="Treasurer" <?php if($member['position']=='Treasurer') echo 'selected'; ?>>Treasurer</option>
            <option value="Member" <?php if($member['position']=='Member') echo 'selected'; ?>>Member</option>
        </select>
        <button type="submit" name="update_membership" class="btn">Save Changes</button>
    </form>
</div>