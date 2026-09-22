<?php
declare(strict_types=1);
namespace App\Modules\Notifications;
use PDO;
final class NotificationTemplateRepository {
    public function __construct(private PDO $db) {}
    public function allForShop(int $shopId): array {
        $s=$this->db->prepare('SELECT id,event_key,template,enabled FROM notification_templates WHERE shop_id=:shop_id ORDER BY id');
        $s->execute(['shop_id'=>$shopId]); return $s->fetchAll();
    }
    public function find(int $shopId,string $event): ?array {
        $s=$this->db->prepare('SELECT id,event_key,template,enabled FROM notification_templates WHERE shop_id=:shop_id AND event_key=:event LIMIT 1');
        $s->execute(['shop_id'=>$shopId,'event'=>$event]); return $s->fetch() ?: null;
    }
    public function update(int $shopId,string $event,string $template,bool $enabled): void {
        $s=$this->db->prepare('UPDATE notification_templates SET template=:template, enabled=:enabled WHERE shop_id=:shop_id AND event_key=:event');
        $s->execute(['shop_id'=>$shopId,'event'=>$event,'template'=>$template,'enabled'=>$enabled?1:0]);
        if($s->rowCount()===0) throw new \RuntimeException('Notification template not found.');
    }
}
