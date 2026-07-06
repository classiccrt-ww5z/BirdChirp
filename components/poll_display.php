<?php
$poll = getPollForPost($post['id']);
if (!$poll) return;
$userVote = isset($uid) && $uid > 0 ? getUserPollVote($poll['id'], $uid) : false;
?>
<div class="poll-container" data-poll-id="<?= $poll['id'] ?>" style="margin-top:10px; padding:10px; border:1px solid #e0e0e0; border-radius:8px; background:#fafafa;">
    <div style="font-weight:600; font-size:14px; margin-bottom:8px;"><?= htmlspecialchars($poll['question']) ?></div>
    <?php foreach ($poll['options'] as $opt): 
        $pct = $poll['total_votes'] > 0 ? round(($opt['votes'] ?? 0) / $poll['total_votes'] * 100) : 0;
    ?>
        <div class="poll-option" style="position:relative; margin-bottom:6px; cursor:<?= $userVote ? 'default' : 'pointer' ?>;" data-option-id="<?= $opt['id'] ?>" onclick="<?= $userVote ? '' : "votePoll({$poll['id']}, {$opt['id']}, this)" ?>">
            <div style="position:relative; z-index:1; display:flex; justify-content:space-between; padding:8px 12px; border-radius:4px; border:1px solid #ccc; background:#fff;">
                <span><?= htmlspecialchars($opt['option_text']) ?></span>
                <?php if ($userVote || $poll['total_votes'] > 0): ?>
                    <span style="font-weight:600;"><?= $pct ?>%</span>
                <?php endif; ?>
            </div>
            <?php if ($userVote || $poll['total_votes'] > 0): ?>
                <div style="position:absolute; top:0; left:0; height:100%; width:<?= $pct ?>%; background:<?= $userVote == $opt['id'] ? '#b3d9ff' : '#e8e8e8' ?>; border-radius:4px; transition:width 0.3s;"></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <div style="font-size:11px; color:#888; margin-top:4px;"><?= $poll['total_votes'] ?> vote<?= $poll['total_votes'] != 1 ? 's' : '' ?></div>
</div>
