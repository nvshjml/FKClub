<?php
session_start();
require 'db_connect.php';

// Fetch the club name for the header
$club_id = $_GET['club_id'];
$club = $conn->query("SELECT club_name FROM CLUB WHERE club_id = $club_id")->fetch_assoc();

// Handle removal from club
if (isset($_POST['remove_member'])) {
    $conn->query("DELETE FROM committee WHERE user_id = '{$_POST['user_id']}' AND club_id = $club_id");
}

// Fetch members
$members = $conn->query("SELECT u.user_id, u.name, c.position FROM committee c JOIN `USER` u ON c.user_id = u.user_id WHERE c.club_id = $club_id");
?>

<h2>Members of <?php echo $club['club_name']; ?></h2>
<table>
    <tr><th>Name</th><th>Role</th><th>Action</th></tr>
    <?php while($m = $members->fetch_assoc()): ?>
    <tr>
        <td><?php echo $m['name']; ?></td>
        <td><?php echo $m['position']; ?></td>
        <td>
            <form method="POST" onsubmit="return confirm('Remove this member?');">
                <input type="hidden" name="user_id" value="<?php echo $m['user_id']; ?>">
                <button type="submit" name="remove_member" class="btn-danger">Remove</button>
            </form>
        </td>
        <td>
    <a href="admin_edit_membership.php?user_id=<?php echo $m['user_id']; ?>&club_id=<?php echo $club_id; ?>" 
       class="btn-sm btn-edit">Edit</a>
    
    <form method="POST" onsubmit="return confirm('Remove this member?');" style="display:inline;">
        <input type="hidden" name="user_id" value="<?php echo $m['user_id']; ?>">
        <button type="submit" name="remove_member" class="btn-danger">Remove</button>
    </form>
</td>
    </tr>
    <?php endwhile; ?>
</table>