<?php
function createPoll($postId, $question, $options) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO polls (post_id, question) VALUES (?, ?)");
    $stmt->execute([$postId, $question]);
    $pollId = $pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO poll_options (poll_id, option_text) VALUES (?, ?)");
    foreach ($options as $opt) {
        $opt = trim($opt);
        if (!empty($opt)) {
            $stmt->execute([$pollId, $opt]);
        }
    }
    return $pollId;
}

function getPollForPost($postId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM polls WHERE post_id = ?");
    $stmt->execute([$postId]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$poll) return null;
    $optStmt = $pdo->prepare("SELECT po.*, (SELECT COUNT(*) FROM poll_votes WHERE option_id = po.id) as votes FROM poll_options po WHERE po.poll_id = ?");
    $optStmt->execute([$poll['id']]);
    $poll['options'] = $optStmt->fetchAll(PDO::FETCH_ASSOC);
    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM poll_votes WHERE poll_id = ?");
    $totalStmt->execute([$poll['id']]);
    $poll['total_votes'] = (int)$totalStmt->fetchColumn();
    return $poll;
}

function votePoll($pollId, $optionId, $userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT 1 FROM poll_votes WHERE poll_id = ? AND user_id = ?");
    $stmt->execute([$pollId, $userId]);
    if ($stmt->fetch()) return false;
    $stmt = $pdo->prepare("SELECT 1 FROM poll_options WHERE id = ? AND poll_id = ?");
    $stmt->execute([$optionId, $pollId]);
    if (!$stmt->fetch()) return false;
    $stmt = $pdo->prepare("INSERT INTO poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)");
    $stmt->execute([$pollId, $optionId, $userId]);
    return true;
}

function getUserPollVote($pollId, $userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT option_id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
    $stmt->execute([$pollId, $userId]);
    return $stmt->fetchColumn();
}
