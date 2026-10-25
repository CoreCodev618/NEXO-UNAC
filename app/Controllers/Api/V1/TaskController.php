<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Libraries\NexoRepository;
use App\Models\PageModel;
use App\Models\TaskModel;
use CodeIgniter\HTTP\ResponseInterface;

final class TaskController extends BaseApiController
{
    private TaskModel $tasks;

    public function __construct()
    {
        $this->tasks = model(TaskModel::class);
    }

    public function create(): ResponseInterface
    {
        $data        = $this->payload();
        $workspaceId = (int) ($data['workspace_id'] ?? 0);

        if ($guard = $this->guardWorkspace($workspaceId, 'edit')) {
            return $guard;
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            return $this->error('El título es obligatorio.', 422, ['title' => 'Requerido.']);
        }

        $status   = $this->enum((string) ($data['status'] ?? 'todo'), ['todo', 'doing', 'done'], 'todo');
        $priority = $this->enum((string) ($data['priority'] ?? 'media'), ['baja', 'media', 'alta'], 'media');

        $due = $this->normalizeDate($data['due_date'] ?? null);
        if ($due === false) {
            return $this->error('due_date inválida; usa YYYY-MM-DD.', 422);
        }

        $pageId = $this->normalizeId($data['page_id'] ?? null);
        if ($pageId === false) {
            return $this->error('page_id inválido.', 422);
        }
        if ($pageId !== null) {
            $page = model(PageModel::class)->find($pageId);
            if ($page === null || (int) $page['workspace_id'] !== $workspaceId) {
                return $this->error('page_id no pertenece al espacio.', 422);
            }
        }

        $assignedTo = $this->normalizeId($data['assigned_to'] ?? null);
        if ($assignedTo === false) {
            return $this->error('assigned_to inválido.', 422);
        }
        if ($assignedTo !== null && ! $this->isWorkspaceMember($workspaceId, $assignedTo)) {
            return $this->error('assigned_to no pertenece al espacio.', 422);
        }

        $order = max(0, (int) ($data['order_index'] ?? $this->nextOrder($workspaceId, $status)));

        $id = $this->tasks->insert([
            'workspace_id' => $workspaceId,
            'page_id'      => $pageId,
            'assigned_to'  => $assignedTo,
            'title'        => mb_substr($title, 0, 255),
            'status'       => $status,
            'priority'     => $priority,
            'order_index'  => $order,
            'due_date'     => $due,
        ]);

        if ($id === false) {
            return $this->error('No se pudo crear la tarea.', 500);
        }

        return $this->ok((new NexoRepository())->task((int) $id), 201);
    }

    public function update(string $id): ResponseInterface
    {
        $taskId = (int) $id;
        $task   = $this->tasks->find($taskId);

        if ($task === null) {
            return $this->error('Tarea no encontrada.', 404);
        }

        if ($guard = $this->guardWorkspace((int) $task['workspace_id'], 'edit')) {
            return $guard;
        }

        $data   = $this->payload();
        $fields = [];

        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);
            if ($title === '') {
                return $this->error('El título es obligatorio.', 422, ['title' => 'Requerido.']);
            }
            $fields['title'] = mb_substr($title, 0, 255);
        }

        if (array_key_exists('status', $data)) {
            $fields['status'] = $this->enum((string) $data['status'], ['todo', 'doing', 'done'], 'todo');
        }

        if (array_key_exists('priority', $data)) {
            $fields['priority'] = $this->enum((string) $data['priority'], ['baja', 'media', 'alta'], 'media');
        }

        if (array_key_exists('due_date', $data)) {
            $due = $this->normalizeDate($data['due_date']);
            if ($due === false) {
                return $this->error('due_date inválida; usa YYYY-MM-DD.', 422);
            }
            $fields['due_date'] = $due;
        }

        if (array_key_exists('assigned_to', $data)) {
            $assignedTo = $this->normalizeId($data['assigned_to']);
            if ($assignedTo === false) {
                return $this->error('assigned_to inválido.', 422);
            }
            if ($assignedTo !== null && ! $this->isWorkspaceMember((int) $task['workspace_id'], $assignedTo)) {
                return $this->error('assigned_to no pertenece al espacio.', 422);
            }
            $fields['assigned_to'] = $assignedTo;
        }

        if (array_key_exists('page_id', $data)) {
            $pageId = $this->normalizeId($data['page_id']);
            if ($pageId === false) {
                return $this->error('page_id inválido.', 422);
            }
            if ($pageId !== null) {
                $page = model(PageModel::class)->find($pageId);
                if ($page === null || (int) $page['workspace_id'] !== (int) $task['workspace_id']) {
                    return $this->error('page_id no pertenece al espacio.', 422);
                }
            }
            $fields['page_id'] = $pageId;
        }

        if (array_key_exists('order_index', $data)) {
            $fields['order_index'] = max(0, (int) $data['order_index']);
        }

        if ($fields !== []) {
            $this->tasks->update($taskId, $fields);
        }

        return $this->ok((new NexoRepository())->task($taskId));
    }

    public function move(string $id): ResponseInterface
    {
        $taskId = (int) $id;
        $task   = $this->tasks->find($taskId);

        if ($task === null) {
            return $this->error('Tarea no encontrada.', 404);
        }

        if ($guard = $this->guardWorkspace((int) $task['workspace_id'], 'edit')) {
            return $guard;
        }

        $data   = $this->payload();
        $status = $this->enum((string) ($data['status'] ?? $task['status']), ['todo', 'doing', 'done'], 'todo');
        $index  = max(0, (int) ($data['order_index'] ?? 0));

        $db = db_connect();
        $db->transBegin();

        try {
            $target = $this->tasks
                ->where('workspace_id', (int) $task['workspace_id'])
                ->where('status', $status)
                ->where('id !=', $taskId)
                ->orderBy('order_index', 'ASC')
                ->orderBy('id', 'ASC')
                ->findAll();

            $ids = array_map(static fn (array $row): int => (int) $row['id'], $target);
            $index = min($index, count($ids));
            array_splice($ids, $index, 0, [$taskId]);

            foreach ($ids as $position => $itemId) {
                $this->tasks->update($itemId, [
                    'status'      => $status,
                    'order_index' => $position,
                ]);
            }

            if ((string) $task['status'] !== $status) {
                $old = $this->tasks
                    ->where('workspace_id', (int) $task['workspace_id'])
                    ->where('status', (string) $task['status'])
                    ->where('id !=', $taskId)
                    ->orderBy('order_index', 'ASC')
                    ->orderBy('id', 'ASC')
                    ->findAll();

                foreach ($old as $position => $row) {
                    $this->tasks->update((int) $row['id'], ['order_index' => $position]);
                }
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'TaskController::move ' . $e->getMessage());
            return $this->error('No se pudo mover la tarea.', 500);
        }

        return $this->ok((new NexoRepository())->task($taskId));
    }

    public function delete(string $id): ResponseInterface
    {
        $taskId = (int) $id;
        $task   = $this->tasks->find($taskId);

        if ($task === null) {
            return $this->error('Tarea no encontrada.', 404);
        }

        if ($guard = $this->guardWorkspace((int) $task['workspace_id'], 'edit')) {
            return $guard;
        }

        $this->tasks->delete($taskId);

        return $this->ok(['id' => $taskId]);
    }

    private function enum(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function normalizeDate(mixed $value): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = (string) $value;

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year) ? $date : false;
    }

    private function normalizeId(mixed $value): int|false|null
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }

        if (! is_numeric($value) || (int) $value <= 0) {
            return false;
        }

        return (int) $value;
    }

    private function isWorkspaceMember(int $workspaceId, int $userId): bool
    {
        return db_connect()
            ->table('workspace_members')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->countAllResults() > 0;
    }

    private function nextOrder(int $workspaceId, string $status): int
    {
        $row = $this->tasks
            ->selectMax('order_index')
            ->where('workspace_id', $workspaceId)
            ->where('status', $status)
            ->first();

        return (int) ($row['order_index'] ?? 0) + 1;
    }
}
