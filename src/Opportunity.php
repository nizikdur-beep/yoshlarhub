<?php

declare(strict_types=1);

class Opportunity
{
    public function __construct(private PDO $db)
    {
    }

    public function categories(): array
    {
        return $this->db->query("
            SELECT id, name, emoji
            FROM categories
            ORDER BY id
        ")->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT o.*, c.name AS category_name, c.emoji
            FROM opportunities o
            JOIN categories c ON c.id = o.category_id
            WHERE o.id = ?
        ");
        $stmt->execute([$id]);
        $res = $stmt->fetch();

        return $res ?: null;
    }

    public function paginateByCategory(int $categoryId, int $page = 1, int $perPage = 5): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 10));
        $offset = ($page - 1) * $perPage;

        // Jami sonini olish
        $countStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM opportunities
            WHERE category_id = :category
              AND is_active = 1
              AND (deadline IS NULL OR deadline >= NOW())
        ");
        $countStmt->execute([':category' => $categoryId]);
        $total = (int) $countStmt->fetchColumn();

        // Sahifadagi elementlar
        $stmt = $this->db->prepare("
            SELECT o.*, c.name AS category_name, c.emoji
            FROM opportunities o
            JOIN categories c ON c.id = o.category_id
            WHERE o.category_id = :category
              AND o.is_active = 1
              AND (o.deadline IS NULL OR o.deadline >= NOW())
            ORDER BY
                CASE WHEN o.deadline IS NULL THEN 1 ELSE 0 END,
                o.deadline ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute([':category' => $categoryId]);
        $items = $stmt->fetchAll();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'totalPages' => (int) ceil($total / $perPage),
        ];
    }

    public function latestByCategory(int $categoryId, int $limit = 10): array
    {
        $limit = max(1, min($limit, 30));

        $stmt = $this->db->prepare("
            SELECT o.*, c.name AS category_name, c.emoji
            FROM opportunities o
            JOIN categories c ON c.id = o.category_id
            WHERE o.category_id = :category
              AND o.is_active = 1
              AND (o.deadline IS NULL OR o.deadline >= NOW())
            ORDER BY
                CASE WHEN o.deadline IS NULL THEN 1 ELSE 0 END,
                o.deadline ASC
            LIMIT {$limit}
        ");

        $stmt->execute([':category' => $categoryId]);

        return $stmt->fetchAll();
    }

    public function search(string $query, int $limit = 10): array
    {
        $limit = max(1, min($limit, 30));
        $query = '%' . $query . '%';

        $stmt = $this->db->prepare("
            SELECT o.*, c.name AS category_name, c.emoji
            FROM opportunities o
            JOIN categories c ON c.id = o.category_id
            WHERE o.is_active = 1
              AND (
                    o.title LIKE :q1
                    OR o.description LIKE :q2
                    OR o.organizer LIKE :q3
                  )
            ORDER BY o.created_at DESC
            LIMIT {$limit}
        ");

        $stmt->execute([
            ':q1' => $query,
            ':q2' => $query,
            ':q3' => $query,
        ]);

        return $stmt->fetchAll();
    }

    public function addBookmark(int $userId, int $opportunityId): void
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO bookmarks
            (user_id, opportunity_id)
            VALUES (:user, :opportunity)
        ");

        $stmt->execute([
            ':user' => $userId,
            ':opportunity' => $opportunityId,
        ]);
    }

    public function removeBookmark(int $userId, int $opportunityId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM bookmarks
            WHERE user_id = :user
              AND opportunity_id = :opportunity
        ");

        $stmt->execute([
            ':user' => $userId,
            ':opportunity' => $opportunityId,
        ]);
    }

    public function bookmarks(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT o.*, c.name AS category_name, c.emoji
            FROM bookmarks b
            JOIN opportunities o
              ON o.id = b.opportunity_id
            JOIN categories c
              ON c.id = o.category_id
            WHERE b.user_id = :user
              AND o.is_active = 1
            ORDER BY b.created_at DESC
        ");

        $stmt->execute([':user' => $userId]);

        return $stmt->fetchAll();
    }
}
