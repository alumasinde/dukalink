<?php

declare(strict_types=1);

namespace App\Modules\Platform;

use PDO;

final class PlatformAuditService
{
    public function __construct(private PDO $db) {}

    public function record(?int $platformUserId, string $action, string $summary, ?string $entityType=null, ?int $entityId=null, array $metadata=[]): void
    {
        $stmt=$this->db->prepare('INSERT INTO platform_audit_logs (platform_user_id,action,entity_type,entity_id,summary,metadata,ip_address,user_agent) VALUES (:user,:action,:type,:entity,:summary,:metadata,:ip,:ua)');
        $stmt->execute([
            'user'=>$platformUserId,'action'=>$action,'type'=>$entityType,'entity'=>$entityId,'summary'=>mb_substr($summary,0,255),
            'metadata'=>$metadata ? json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
            'ip'=>$_SERVER['REMOTE_ADDR']??null,'ua'=>mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500) ?: null,
        ]);
    }
}
