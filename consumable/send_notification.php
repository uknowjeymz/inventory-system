<?php
/**
 * Helper function to send notifications across modules with real-time updates
 */

function sendNotification($db, $user_id, $type, $title, $message, $link = null, $reference_id = null, $reference_type = null) {
    $query = "INSERT INTO notifications (user_id, type, title, message, link, reference_id, reference_type, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$user_id, $type, $title, $message, $link, $reference_id, $reference_type]);
    
    if ($result) {
        $notification_id = $db->lastInsertId();
        
        // Get user's name for the notification
        $user_query = "SELECT full_name FROM users WHERE id = ?";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->execute([$user_id]);
        $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
        $user_name = $user_data ? $user_data['full_name'] : 'Unknown';
        
        // Trigger real-time event via Pusher
        triggerRealtimeNotification($user_id, [
            'id' => $notification_id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'reference_id' => $reference_id,
            'reference_type' => $reference_type,
            'user_name' => $user_name,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    return $result;
}

function notifyAdmins($db, $type, $title, $message, $link = null, $reference_id = null, $reference_type = null) {
    // Debug - log that we're here
    error_log("notifyAdmins called - Type: $type, Title: $title");
    
    // Get all admin users from the users table
    $admin_query = "SELECT id FROM users WHERE role = 'admin'";
    $admin_stmt = $db->prepare($admin_query);
    $admin_stmt->execute();
    $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($admins) . " admins");
    
    $success = true;
    foreach ($admins as $admin) {
        error_log("Sending notification to admin ID: " . $admin['id']);
        $result = sendNotification($db, $admin['id'], $type, $title, $message, $link, $reference_id, $reference_type);
        if (!$result) {
            error_log("Failed to send notification to admin ID: " . $admin['id']);
            $success = false;
        }
    }
    
    // Also trigger a broadcast event for all admins
    triggerRealtimeBroadcast('admin-notifications', [
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'link' => $link,
        'reference_id' => $reference_id,
        'reference_type' => $reference_type,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    return $success;
}

function notifyByRole($db, $role, $type, $title, $message, $link = null, $reference_id = null, $reference_type = null) {
    $user_query = "SELECT id FROM users WHERE role = ?";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute([$role]);
    $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $success = true;
    foreach ($users as $user) {
        $result = sendNotification($db, $user['id'], $type, $title, $message, $link, $reference_id, $reference_type);
        if (!$result) $success = false;
    }
    
    // Trigger broadcast for this role
    triggerRealtimeBroadcast($role . '-notifications', [
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'link' => $link,
        'reference_id' => $reference_id,
        'reference_type' => $reference_type,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    return $success;
}

// NEW: Function to trigger real-time notification for a specific user
function triggerRealtimeNotification($user_id, $data) {
    try {
        $pusher = require __DIR__ . '/../config/pusher.php';
        
        // Trigger on user-specific channel
        $pusher->trigger('user-' . $user_id, 'new-notification', $data);
        
        // Also trigger on general channel for online status
        $pusher->trigger('presence-notifications', 'user-update', [
            'user_id' => $user_id,
            'has_unread' => true
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log('Pusher error: ' . $e->getMessage());
        return false;
    }
}

// NEW: Function to broadcast to all admins
function triggerRealtimeBroadcast($channel, $data) {
    try {
        $pusher = require __DIR__ . '/../config/pusher.php';
        $pusher->trigger($channel, 'new-notification', $data);
        return true;
    } catch (Exception $e) {
        error_log('Pusher error: ' . $e->getMessage());
        return false;
    }
}
?>