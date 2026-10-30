<?php
namespace App\Models;

use PDO;

class ReportModel extends BaseModel {
    protected string $table = 'reports';
    protected string $primaryKey = 'id';

    public function __construct(?PDO $pdo = null) {
        if ($pdo === null) {
            global $pdo;
            $pdo = $pdo ?? null;
        }
        parent::__construct($pdo);
    }

    public function getPropertiesByStatus(?string $status = null, ?string $type = null): array {
        $sql = "SELECT status, COUNT(*) as count FROM properties WHERE 1=1";
        $params = [];
        
        if ($status) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
        if ($type) {
            $sql .= " AND type = :type";
            $params[':type'] = $type;
        }
        
        $sql .= " GROUP BY status ORDER BY count DESC";
        return $this->query($sql, $params);
    }
}
