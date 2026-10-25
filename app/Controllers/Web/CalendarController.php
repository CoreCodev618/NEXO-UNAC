<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Libraries\FrontendDemo;
use CodeIgniter\Exceptions\PageNotFoundException;
use DateTimeImmutable;
use DateTimeZone;

class CalendarController extends BaseWebController
{
    public function index(string $workspaceId): string
    {
        if ($this->isPreview()) {
            $workspace = FrontendDemo::workspace((int) $workspaceId);
            $tasks     = $workspace !== null ? FrontendDemo::tasks((int) $workspaceId) : [];
        } else {
            $workspace = $this->repository()->workspace((int) $workspaceId, (int) auth()->id());
            $tasks     = $workspace !== null ? $this->repository()->tasks((int) $workspaceId) : [];
        }

        if ($workspace === null) {
            throw PageNotFoundException::forPageNotFound('No encontramos ese espacio.');
        }

        $this->setWorkspace($workspace);

        $timezone = new DateTimeZone('America/Lima');
        $month    = $this->request->getGet('month');
        $month    = is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1
            ? $month
            : (new DateTimeImmutable('now', $timezone))->format('Y-m');

        $first = new DateTimeImmutable($month . '-01', $timezone);
        $start = $first->modify('-' . ((int) $first->format('N') - 1) . ' days');
        $today = (new DateTimeImmutable('today', $timezone))->format('Y-m-d');

        $byDate = [];

        foreach ($tasks as $task) {
            if ($task['due_date'] !== null) {
                $byDate[$task['due_date']][] = $task;
            }
        }

        $days = [];

        for ($i = 0; $i < 42; $i++) {
            $date    = $start->modify('+' . $i . ' days');
            $dateKey = $date->format('Y-m-d');
            $dayTasks = $byDate[$dateKey] ?? [];

            usort($dayTasks, static function (array $a, array $b): int {
                $weight = ['alta' => 0, 'media' => 1, 'baja' => 2];

                return ($weight[$a['priority']] ?? 1) <=> ($weight[$b['priority']] ?? 1)
                    ?: strcmp((string) $a['title'], (string) $b['title']);
            });

            $days[] = [
                'date'       => $dateKey,
                'day'        => (int) $date->format('j'),
                'in_month'   => $date->format('Y-m') === $first->format('Y-m'),
                'is_today'   => $dateKey === $today,
                'is_weekend' => (int) $date->format('N') >= 6,
                'tasks'      => array_slice($dayTasks, 0, 3),
                'task_count' => count($dayTasks),
            ];
        }

        $monthTasks = array_values(array_filter(
            $tasks,
            static fn (array $task): bool => $task['due_date'] !== null
                && str_starts_with((string) $task['due_date'], $first->format('Y-m')),
        ));

        $agenda = [];

        foreach ($monthTasks as $task) {
            $agenda[$task['due_date']][] = $task;
        }

        ksort($agenda);

        $agendaGroups = [];

        foreach ($agenda as $date => $dateTasks) {
            $dateObject = new DateTimeImmutable($date, $timezone);
            $agendaGroups[] = [
                'date'  => $date,
                'label' => (int) $dateObject->format('j') . ' ' . nexo_month_name((int) $dateObject->format('n'), true),
                'weekday' => nexo_weekday_short((int) $dateObject->format('N') - 1),
                'tasks' => $dateTasks,
            ];
        }

        $open = array_values(array_filter($tasks, static fn (array $task): bool => $task['status'] !== 'done' && $task['due_date'] !== null));
        usort($open, static fn (array $a, array $b): int => strcmp((string) $a['due_date'], (string) $b['due_date']));

        $view = $this->request->getGet('view') === 'agenda' ? 'agenda' : 'month';
        $base = nexo_url($this->prefix(), 'workspaces/' . $workspace['id'] . '/calendar');

        return $this->render('calendar/index', [
            'pageTitle'   => 'Calendario · ' . $workspace['name'],
            'topbarTitle' => 'Calendario',
            'activeNav'   => 'workspaces',
            'workspace'   => $workspace,
            'month'       => $month,
            'monthTitle'  => nexo_month_name((int) $first->format('n')) . ' ' . $first->format('Y'),
            'days'        => $days,
            'agenda'      => $agendaGroups,
            'view'        => $view,
            'tasks'       => $tasks,
            'upcoming'    => array_slice($open, 0, 4),
            'today'       => $today,
            'prevMonth'   => $first->modify('-1 month')->format('Y-m'),
            'nextMonth'   => $first->modify('+1 month')->format('Y-m'),
            'baseUrl'     => $base,
            'monthUrl'    => $base . '?month=' . $month . '&view=month',
            'agendaUrl'   => $base . '?month=' . $month . '&view=agenda',
            'prevUrl'     => $base . '?month=' . $first->modify('-1 month')->format('Y-m') . '&view=' . $view,
            'nextUrl'     => $base . '?month=' . $first->modify('+1 month')->format('Y-m') . '&view=' . $view,
            'todayUrl'    => $base . '?month=' . (new DateTimeImmutable('now', $timezone))->format('Y-m') . '&view=' . $view,
            'weekdays'    => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
        ]);
    }
}
