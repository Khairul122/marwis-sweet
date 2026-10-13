<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;

final class AuditController extends BaseController
{
    public function index(): string
    {
        $user = session('user');
        if (($user['role'] ?? '') !== 'owner') {
            throw PageNotFoundException::forPageNotFound();
        }

        $eventType = $this->queryText('event_type', 100);
        $objectType = $this->queryText('object_type', 80);
        $from = $this->validDate($this->queryText('from', 10));
        $to = $this->validDate($this->queryText('to', 10));
        $page = max(1, filter_var($this->request->getGet('page'), FILTER_VALIDATE_INT) ?: 1);
        $pageSize = 50;
        $database = db_connect();
        $builder = $database->table('audit_events')
            ->join('users', 'users.id = audit_events.actor_user_id')
            ->select('audit_events.*, users.name AS actor_name, users.username AS actor_username');
        if ($eventType !== '') { $builder->where('audit_events.event_type', $eventType); }
        if ($objectType !== '') { $builder->where('audit_events.object_type', $objectType); }
        if ($from !== null) { $builder->where('audit_events.created_at >=', $from . ' 00:00:00'); }
        if ($to !== null) { $builder->where('audit_events.created_at <=', $to . ' 23:59:59'); }
        $total = (clone $builder)->countAllResults();
        $events = $builder->orderBy('audit_events.created_at', 'DESC')->orderBy('audit_events.id', 'DESC')
            ->limit($pageSize, ($page - 1) * $pageSize)->get()->getResultArray();
        foreach ($events as &$event) {
            $event['before_values'] = $event['before_values'] ? json_decode($event['before_values'], true, 16, JSON_THROW_ON_ERROR) : [];
            $event['after_values'] = $event['after_values'] ? json_decode($event['after_values'], true, 16, JSON_THROW_ON_ERROR) : [];
        }
        unset($event);

        $filters = db_connect()->table('audit_events')->select('event_type')->distinct()->orderBy('event_type')->get()->getResultArray();
        $objectTypes = db_connect()->table('audit_events')->select('object_type')->distinct()->orderBy('object_type')->get()->getResultArray();
        return view('panel/audit/index', [
            'title' => 'Riwayat perubahan', 'user' => $user, 'events' => $events,
            'eventTypes' => $filters, 'objectTypes' => $objectTypes,
            'selectedEventType' => $eventType, 'selectedObjectType' => $objectType,
            'from' => $from ?? '', 'to' => $to ?? '', 'page' => $page,
            'pageCount' => max(1, (int) ceil($total / $pageSize)), 'total' => $total,
        ]);
    }

    private function queryText(string $key, int $maxLength): string
    {
        $value = $this->request->getGet($key);
        return is_string($value) ? mb_substr(trim($value), 0, $maxLength) : '';
    }

    private function validDate(string $value): ?string
    {
        if ($value === '') { return null; }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}
