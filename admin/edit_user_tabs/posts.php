<?php $s=$pdo->prepare("SELECT p.*,u.username,u.display_name,u.avatar FROM posts p JOIN users u ON p.user_id=u.id WHERE p.user_id=? ORDER BY p.created_at DESC");$s->execute([$selectedUser['id']]);$posts=$s->fetchAll(); ?>
<div class="panel panel-default">
<div class="panel-body">
<?php if($posts): foreach($posts as $p):?>
<div class="media" style="border-bottom:1px solid #eee;padding-bottom:8px;margin-bottom:8px;">
  <div class="media-left"><img src="/images/avatars/<?=e($p['avatar']??'default.png')?>" style="width:32px;height:32px;" class="media-object img-thumbnail"></div>
  <div class="media-body">
    <b><?=e($p['display_name']?:$p['username'])?></b> <span class="text-muted"><?=$p['created_at']?></span>
    <p style="margin:4px 0;"><?=nl2br(e($p['content']))?></p>
    <?php if($p['image']):?><img src="/images/posts/<?=e($p['image'])?>" style="max-width:200px;max-height:150px;" class="img-thumbnail"><?php endif;?>
    <?php if($p['video']):?><video controls style="max-width:200px;max-height:150px;" class="img-thumbnail"><source src="/videos/posts/<?=e($p['video'])?>" type="video/mp4"></video><?php endif;?>
    <p style="margin:6px 0 0;"><a href="?delete_post=<?=$p['id']?>&user_id=<?=$selectedUser['id']?>&csrf=<?=$adminCsrf?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete?')">Delete</a></p>
  </div>
</div>
<?php endforeach; else:?><p class="text-muted" style="text-align:center;padding:12px;">No posts.</p><?php endif;?>
</div></div>