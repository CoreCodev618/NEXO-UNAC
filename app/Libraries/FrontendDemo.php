<?php

declare(strict_types=1);

namespace App\Libraries;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Datos de demostración del frontend.
 *
 * Esta capa existe solo mientras los endpoints F3–F5 del backend no estén
 * disponibles. Su forma sigue los campos reales de las migraciones para que
 * reemplazarla por respuestas de API no obligue a reescribir las vistas.
 */
final class FrontendDemo
{
    private const TIMEZONE = 'America/Lima';

    public static function user(): array
    {
        return [
            'id'         => 1,
            'name'       => 'Sebastián Rabanal',
            'username'   => 'sebastian.rabanal',
            'email'      => 'sebastian.rabanal@unac.edu.pe',
            'faculty'    => 'FIIS',
            'role'       => 'admin',
            'role_label' => 'Administrador',
            'groups'     => ['admin'],
            'initials'   => 'SR',
            'tone'       => 'wine',
            'is_demo'    => true,
        ];
    }

    public static function members(): array
    {
        return [
            1 => [
                'id'       => 1,
                'name'     => 'Sebastián Rabanal',
                'email'    => 'sebastian.rabanal@unac.edu.pe',
                'initials' => 'SR',
                'tone'     => 'wine',
                'role'     => 'owner',
                'group'    => 'admin',
            ],
            2 => [
                'id'       => 2,
                'name'     => 'Ana Torres',
                'email'    => 'ana.torres@unac.edu.pe',
                'initials' => 'AT',
                'tone'     => 'gold',
                'role'     => 'editor',
                'group'    => 'docente',
            ],
            3 => [
                'id'       => 3,
                'name'     => 'Luis Quispe',
                'email'    => 'luis.quispe@unac.edu.pe',
                'initials' => 'LQ',
                'tone'     => 'sage',
                'role'     => 'editor',
                'group'    => 'alumno',
            ],
            4 => [
                'id'       => 4,
                'name'     => 'Camila Rojas',
                'email'    => 'camila.rojas@unac.edu.pe',
                'initials' => 'CR',
                'tone'     => 'rose',
                'role'     => 'viewer',
                'group'    => 'alumno',
            ],
        ];
    }

    public static function workspaces(): array
    {
        return [
            [
                'id'          => 1,
                'name'        => 'EP Sistemas',
                'type'        => 'EP-Sistemas',
                'type_label'  => 'Escuela de Sistemas',
                'type_full'   => 'Escuela Profesional de Ingeniería de Sistemas',
                'summary'     => 'Apuntes, sílabos y proyectos del salón de Sistemas.',
                'faculty'     => 'FIIS',
                'icon'        => 'book-open',
                'tone'        => 'wine',
                'cover'       => 'wine',
                'member_ids'  => [1, 2, 3, 4],
                'pages_count' => 5,
                'open_tasks'  => 4,
                'updated_at'  => self::hoursAgo(2),
            ],
            [
                'id'          => 2,
                'name'        => 'EP Industrial',
                'type'        => 'EP-Industrial',
                'type_label'  => 'Escuela Industrial',
                'type_full'   => 'Escuela Profesional de Ingeniería Industrial',
                'summary'     => 'Investigación de operaciones, laboratorios y casos.',
                'faculty'     => 'FIIS',
                'icon'        => 'building',
                'tone'        => 'gold',
                'cover'       => 'gold',
                'member_ids'  => [1, 2, 3],
                'pages_count' => 2,
                'open_tasks'  => 1,
                'updated_at'  => self::hoursAgo(3),
            ],
            [
                'id'          => 3,
                'name'        => 'Club IA · FIIS',
                'type'        => 'club',
                'type_label'  => 'Club estudiantil',
                'type_full'   => 'Organización estudiantil',
                'summary'     => 'Ruta de aprendizaje, sesiones y proyectos del club.',
                'faculty'     => 'FIIS',
                'icon'        => 'sparkles',
                'tone'        => 'sage',
                'cover'       => 'sage',
                'member_ids'  => [1, 2, 4],
                'pages_count' => 3,
                'open_tasks'  => 2,
                'updated_at'  => self::hoursAgo(5),
            ],
        ];
    }

    public static function workspace(int $id): ?array
    {
        foreach (self::workspaces() as $workspace) {
            if ((int) $workspace['id'] === $id) {
                return self::hydrateWorkspace($workspace);
            }
        }

        return null;
    }

    /**
     * Árbol de páginas de un espacio. Incluye children anidados.
     *
     * @return list<array<string, mixed>>
     */
    public static function pages(int $workspaceId): array
    {
        $all = self::allPages();
        $byId = [];

        foreach ($all as $page) {
            if ((int) $page['workspace_id'] !== $workspaceId) {
                continue;
            }

            $page['children'] = [];
            $byId[(int) $page['id']] = $page;
        }

        $roots = [];

        foreach ($byId as $id => &$page) {
            $parentId = $page['parent_id'];

            if ($parentId !== null && isset($byId[(int) $parentId])) {
                continue;
            }

            $roots[] = &$page;
        }

        unset($page);

        $sort = static function (array &$nodes) use (&$sort, &$byId): void {
            usort($nodes, static fn (array $a, array $b): int => ((int) $a['order_index'] <=> (int) $b['order_index']) ?: strcmp((string) $a['title'], (string) $b['title']));

            foreach ($nodes as &$node) {
                $children = [];

                foreach ($byId as &$candidate) {
                    if ((int) ($candidate['parent_id'] ?? 0) === (int) $node['id']) {
                        $children[] = &$candidate;
                    }
                }

                unset($candidate);

                if ($children !== []) {
                    $sort($children);
                }

                $node['children'] = $children;
            }

            unset($node);
        };

        $sort($roots);

        return $roots;
    }

    public static function page(int $id): ?array
    {
        foreach (self::allPages() as $page) {
            if ((int) $page['id'] !== $id) {
                continue;
            }

            $workspace = self::workspace((int) $page['workspace_id']);
            $members   = self::members();

            return [
                'id'           => (int) $page['id'],
                'workspace_id' => (int) $page['workspace_id'],
                'parent_id'    => $page['parent_id'],
                'title'        => $page['title'],
                'icon'         => $page['icon'],
                'cover'        => $page['cover'],
                'order_index'  => $page['order_index'],
                'created_at'   => $page['created_at'],
                'updated_at'   => $page['updated_at'],
                'author'       => $page['author'],
                'workspace'    => $workspace,
                'tags'         => $page['tags'],
                'blocks'       => $page['blocks'],
                'comments'     => self::comments((int) $page['id']),
                'attachments'  => self::attachments((int) $page['id']),
                'members'      => $members,
                'word_count'   => self::wordCount($page['blocks']),
                'read_time'    => max(1, (int) ceil(self::wordCount($page['blocks']) / 210)),
            ];
        }

        return null;
    }

    public static function newPage(int $workspaceId = 1): array
    {
        $workspace = self::workspace($workspaceId) ?? self::workspace(1);

        return [
            'id'           => 0,
            'workspace_id' => (int) $workspace['id'],
            'parent_id'    => null,
            'title'        => 'Página sin título',
            'icon'         => 'file-text',
            'cover'        => 'rose',
            'order_index'  => 99,
            'created_at'   => self::now(),
            'updated_at'   => self::now(),
            'author'       => self::user()['name'] ?? 'Estudiante',
            'workspace'    => $workspace,
            'tags'         => [],
            'blocks'       => [],
            'comments'     => [],
            'attachments'  => [],
            'members'      => self::members(),
            'word_count'   => 0,
            'read_time'    => 1,
            'is_new'       => true,
        ];
    }

    /**
     * Todas las páginas planas, ordenadas por actualización.
     *
     * @return list<array<string, mixed>>
     */
    public static function allPages(): array
    {
        $pages = [
            [
                'id'           => 1,
                'workspace_id' => 1,
                'parent_id'    => null,
                'title'        => 'Semana 6 — Programación Entera',
                'icon'         => 'book-open',
                'cover'        => 'wine',
                'order_index'  => 0,
                'tags'         => ['PL', 'Semana 6'],
                'author'       => 'Ana Torres',
                'created_at'   => self::daysAgo(8),
                'updated_at'   => self::hoursAgo(2),
                'blocks'       => self::blocksPageOne(),
            ],
            [
                'id'           => 2,
                'workspace_id' => 1,
                'parent_id'    => 1,
                'title'        => 'Modelo de programación lineal',
                'icon'         => 'table',
                'cover'        => 'paper',
                'order_index'  => 0,
                'tags'         => ['PL', 'Modelo'],
                'author'       => 'Sebastián Rabanal',
                'created_at'   => self::daysAgo(7),
                'updated_at'   => self::daysAgo(1),
                'blocks'       => self::blocksPageTwo(),
            ],
            [
                'id'           => 3,
                'workspace_id' => 1,
                'parent_id'    => 1,
                'title'        => 'Ejercicios resueltos',
                'icon'         => 'check-square',
                'cover'        => 'gold',
                'order_index'  => 1,
                'tags'         => ['PL', 'Práctica'],
                'author'       => 'Ana Torres',
                'created_at'   => self::daysAgo(6),
                'updated_at'   => self::daysAgo(2),
                'blocks'       => self::blocksPageThree(),
            ],
            [
                'id'           => 4,
                'workspace_id' => 1,
                'parent_id'    => null,
                'title'        => 'Sílabus 2026-B',
                'icon'         => 'graduation-cap',
                'cover'        => 'sage',
                'order_index'  => 1,
                'tags'         => ['Sílabus'],
                'author'       => 'Sebastián Rabanal',
                'created_at'   => self::daysAgo(14),
                'updated_at'   => self::daysAgo(4),
                'blocks'       => self::blocksPageFour(),
            ],
            [
                'id'           => 5,
                'workspace_id' => 1,
                'parent_id'    => null,
                'title'        => 'Proyecto final — NEXO',
                'icon'         => 'compass',
                'cover'        => 'dark',
                'order_index'  => 2,
                'tags'         => ['Proyecto', 'NEXO'],
                'author'       => 'Sebastián Rabanal',
                'created_at'   => self::daysAgo(20),
                'updated_at'   => self::daysAgo(3),
                'blocks'       => self::blocksPageFive(),
            ],
            [
                'id'           => 6,
                'workspace_id' => 2,
                'parent_id'    => null,
                'title'        => 'Investigación de operaciones',
                'icon'         => 'table',
                'cover'        => 'gold',
                'order_index'  => 0,
                'tags'         => ['IO', 'Curso'],
                'author'       => 'Luis Quispe',
                'created_at'   => self::daysAgo(12),
                'updated_at'   => self::hoursAgo(3),
                'blocks'       => self::blocksPageSix(),
            ],
            [
                'id'           => 7,
                'workspace_id' => 2,
                'parent_id'    => 6,
                'title'        => 'Laboratorio 3 — Inventarios',
                'icon'         => 'flask',
                'cover'        => 'sage',
                'order_index'  => 0,
                'tags'         => ['Laboratorio'],
                'author'       => 'Luis Quispe',
                'created_at'   => self::daysAgo(5),
                'updated_at'   => self::daysAgo(1),
                'blocks'       => self::blocksPageSeven(),
            ],
            [
                'id'           => 8,
                'workspace_id' => 3,
                'parent_id'    => null,
                'title'        => 'Ruta de aprendizaje — Club IA',
                'icon'         => 'sparkles',
                'cover'        => 'wine',
                'order_index'  => 0,
                'tags'         => ['IA', 'Ruta'],
                'author'       => 'Camila Rojas',
                'created_at'   => self::daysAgo(10),
                'updated_at'   => self::daysAgo(2),
                'blocks'       => self::blocksPageEight(),
            ],
            [
                'id'           => 9,
                'workspace_id' => 3,
                'parent_id'    => 8,
                'title'        => 'Sesión 04 — RAG',
                'icon'         => 'book-open',
                'cover'        => 'ink',
                'order_index'  => 0,
                'tags'         => ['RAG', 'Sesión'],
                'author'       => 'Sebastián Rabanal',
                'created_at'   => self::daysAgo(6),
                'updated_at'   => self::daysAgo(1),
                'blocks'       => self::blocksPageNine(),
            ],
            [
                'id'           => 10,
                'workspace_id' => 3,
                'parent_id'    => 8,
                'title'        => 'Notas del pipeline',
                'icon'         => 'code',
                'cover'        => 'paper',
                'order_index'  => 1,
                'tags'         => ['Código'],
                'author'       => 'Sebastián Rabanal',
                'created_at'   => self::daysAgo(4),
                'updated_at'   => self::hoursAgo(5),
                'blocks'       => self::blocksPageTen(),
            ],
        ];

        return $pages;
    }

    public static function recentPages(int $limit = 5): array
    {
        $pages = self::allPages();

        usort($pages, static fn (array $a, array $b): int => strcmp((string) $b['updated_at'], (string) $a['updated_at']));

        $workspaces = [];

        foreach (self::workspaces() as $workspace) {
            $workspaces[(int) $workspace['id']] = $workspace;
        }

        return array_map(static function (array $page) use ($workspaces): array {
            $workspace = $workspaces[(int) $page['workspace_id']] ?? null;

            $page['workspace_name'] = $workspace['name'] ?? 'Espacio';
            $page['workspace_icon'] = $workspace['icon'] ?? 'folder';
            $page['workspace_tone'] = $workspace['tone'] ?? 'paper';
            $page['workspace_type'] = $workspace['type'] ?? null;

            return $page;
        }, array_slice($pages, 0, $limit));
    }

    /**
     * Tareas con campos derivados listos para renderizar.
     *
     * @return list<array<string, mixed>>
     */
    public static function tasks(?int $workspaceId = null): array
    {
        $tasks = [
            [
                'id'           => 1,
                'workspace_id' => 1,
                'page_id'      => 1,
                'assigned_to'  => 2,
                'title'        => 'Terminar la guía de programación entera',
                'status'       => 'doing',
                'priority'     => 'alta',
                'order_index'  => 0,
                'due_date'     => self::dateIn(1),
                'created_at'   => self::daysAgo(3),
                'updated_at'   => self::hoursAgo(4),
            ],
            [
                'id'           => 2,
                'workspace_id' => 1,
                'page_id'      => 1,
                'assigned_to'  => 1,
                'title'        => 'Subir los apuntes de la Semana 6',
                'status'       => 'todo',
                'priority'     => 'media',
                'order_index'  => 0,
                'due_date'     => self::dateIn(0),
                'created_at'   => self::daysAgo(2),
                'updated_at'   => self::hoursAgo(2),
            ],
            [
                'id'           => 3,
                'workspace_id' => 1,
                'page_id'      => 4,
                'assigned_to'  => 1,
                'title'        => 'Revisar el sílabus 2026-B',
                'status'       => 'todo',
                'priority'     => 'baja',
                'order_index'  => 1,
                'due_date'     => self::dateIn(3),
                'created_at'   => self::daysAgo(2),
                'updated_at'   => self::daysAgo(1),
            ],
            [
                'id'           => 4,
                'workspace_id' => 1,
                'page_id'      => 5,
                'assigned_to'  => 1,
                'title'        => 'Preparar la demo de NEXO',
                'status'       => 'doing',
                'priority'     => 'alta',
                'order_index'  => 1,
                'due_date'     => self::dateIn(2),
                'created_at'   => self::daysAgo(5),
                'updated_at'   => self::hoursAgo(6),
            ],
            [
                'id'           => 5,
                'workspace_id' => 1,
                'page_id'      => 5,
                'assigned_to'  => 2,
                'title'        => 'Cerrar el acta de la reunión',
                'status'       => 'done',
                'priority'     => 'media',
                'order_index'  => 0,
                'due_date'     => self::dateIn(-1),
                'created_at'   => self::daysAgo(4),
                'updated_at'   => self::hoursAgo(20),
            ],
            [
                'id'           => 6,
                'workspace_id' => 2,
                'page_id'      => 7,
                'assigned_to'  => 3,
                'title'        => 'Resolver el caso de inventarios',
                'status'       => 'doing',
                'priority'     => 'alta',
                'order_index'  => 0,
                'due_date'     => self::dateIn(1),
                'created_at'   => self::daysAgo(6),
                'updated_at'   => self::hoursAgo(8),
            ],
            [
                'id'           => 7,
                'workspace_id' => 2,
                'page_id'      => 6,
                'assigned_to'  => 1,
                'title'        => 'Leer el capítulo 4 de Taha',
                'status'       => 'todo',
                'priority'     => 'media',
                'order_index'  => 0,
                'due_date'     => self::dateIn(4),
                'created_at'   => self::daysAgo(3),
                'updated_at'   => self::daysAgo(1),
            ],
            [
                'id'           => 8,
                'workspace_id' => 2,
                'page_id'      => 7,
                'assigned_to'  => 3,
                'title'        => 'Entregar el laboratorio 3',
                'status'       => 'done',
                'priority'     => 'baja',
                'order_index'  => 0,
                'due_date'     => self::dateIn(-2),
                'created_at'   => self::daysAgo(8),
                'updated_at'   => self::daysAgo(2),
            ],
            [
                'id'           => 9,
                'workspace_id' => 3,
                'page_id'      => 9,
                'assigned_to'  => 4,
                'title'        => 'Preparar la sesión de RAG',
                'status'       => 'doing',
                'priority'     => 'alta',
                'order_index'  => 0,
                'due_date'     => self::dateIn(5),
                'created_at'   => self::daysAgo(4),
                'updated_at'   => self::hoursAgo(9),
            ],
            [
                'id'           => 10,
                'workspace_id' => 3,
                'page_id'      => 10,
                'assigned_to'  => 1,
                'title'        => 'Documentar el pipeline de embeddings',
                'status'       => 'todo',
                'priority'     => 'media',
                'order_index'  => 0,
                'due_date'     => self::dateIn(6),
                'created_at'   => self::daysAgo(3),
                'updated_at'   => self::hoursAgo(5),
            ],
            [
                'id'           => 11,
                'workspace_id' => 3,
                'page_id'      => 8,
                'assigned_to'  => 1,
                'title'        => 'Publicar la ruta de aprendizaje',
                'status'       => 'done',
                'priority'     => 'baja',
                'order_index'  => 0,
                'due_date'     => self::dateIn(-5),
                'created_at'   => self::daysAgo(9),
                'updated_at'   => self::daysAgo(5),
            ],
        ];

        $members   = self::members();
        $pages     = [];
        $workspaces = [];

        foreach (self::allPages() as $page) {
            $pages[(int) $page['id']] = $page;
        }

        foreach (self::workspaces() as $workspace) {
            $workspaces[(int) $workspace['id']] = $workspace;
        }

        $tasks = array_values(array_filter($tasks, static fn (array $task): bool => $workspaceId === null || (int) $task['workspace_id'] === $workspaceId));

        return array_map(static function (array $task) use ($members, $pages, $workspaces): array {
            $assignee   = $members[(int) $task['assigned_to']] ?? null;
            $page       = $pages[(int) $task['page_id']] ?? null;
            $workspace  = $workspaces[(int) $task['workspace_id']] ?? null;
            $isOverdue  = $task['status'] !== 'done'
                && $task['due_date'] !== null
                && $task['due_date'] < self::today();

            return [
                ...$task,
                'assignee_name'     => $assignee['name'] ?? 'Sin asignar',
                'assignee_initials' => $assignee['initials'] ?? '—',
                'assignee_tone'     => $assignee['tone'] ?? 'paper',
                'page_title'        => $page['title'] ?? null,
                'workspace_name'    => $workspace['name'] ?? null,
                'due_label'         => $task['due_date'] !== null ? nexo_date($task['due_date']) : 'Sin fecha',
                'is_overdue'        => $isOverdue,
            ];
        }, $tasks);
    }

    public static function dashboard(): array
    {
        $recent = self::recentPages(6);
        $tasks  = self::tasks();
        $open   = array_values(array_filter($tasks, static fn (array $task): bool => $task['status'] !== 'done'));

        usort($open, static function (array $a, array $b): int {
            $weight = ['alta' => 0, 'media' => 1, 'baja' => 2];
            $aDue   = $a['due_date'] ?? '9999-12-31';
            $bDue   = $b['due_date'] ?? '9999-12-31';

            return strcmp($aDue, $bDue)
                ?: (($weight[$a['priority']] ?? 3) <=> ($weight[$b['priority']] ?? 3));
        });

        $workspaces = self::workspaces();

        foreach ($workspaces as &$workspace) {
            $workspace = self::hydrateWorkspace($workspace);
        }

        unset($workspace);

        return [
            'user'           => self::user(),
            'workspaces'     => $workspaces,
            'recent_pages'   => $recent,
            'featured_page'  => $recent[0] ?? null,
            'tasks'          => array_slice($open, 0, 4),
            'top_tasks'      => $open,
            'counts'         => [
                'workspaces' => count($workspaces),
                'pages'      => count(self::allPages()),
                'open_tasks' => count($open),
            ],
            'events'         => self::events(),
            'today'          => self::today(),
        ];
    }

    /**
     * El Radar es backend-owned. En demo no inventamos eventos.
     *
     * @return list<array<string, mixed>>
     */
    public static function events(): array
    {
        return [];
    }

    public static function comments(int $pageId): array
    {
        if ($pageId !== 1) {
            return [];
        }

        return [
            [
                'id'         => 1,
                'page_id'    => 1,
                'user_id'    => 2,
                'content'    => 'Agregué el ejemplo de ramificación y acotación. Falta revisar la tabla de complejidades.',
                'created_at' => self::hoursAgo(3),
                'author'     => 'Ana Torres',
                'initials'   => 'AT',
                'tone'       => 'gold',
            ],
            [
                'id'         => 2,
                'page_id'    => 1,
                'user_id'    => 1,
                'content'    => 'Lo reviso antes de la sesión. También quiero conectar este ejemplo con el proyecto final.',
                'created_at' => self::hoursAgo(1),
                'author'     => 'Sebastián Rabanal',
                'initials'   => 'SR',
                'tone'       => 'wine',
            ],
        ];
    }

    public static function attachments(int $pageId): array
    {
        $files = match ($pageId) {
            1 => [
                ['id' => 1, 'file_path' => 'Semana 6 - guía.pdf', 'file_type' => 'PDF', 'file_size' => 1_840_000, 'created_at' => self::daysAgo(2)],
                ['id' => 2, 'file_path' => 'Ejercicios resueltos.xlsx', 'file_type' => 'XLSX', 'file_size' => 245_760, 'created_at' => self::daysAgo(2)],
                ['id' => 3, 'file_path' => 'PC1 resuelta.pdf', 'file_type' => 'PDF', 'file_size' => 3_240_000, 'created_at' => self::daysAgo(5)],
            ],
            5 => [
                ['id' => 4, 'file_path' => 'Brief NEXO.pdf', 'file_type' => 'PDF', 'file_size' => 980_000, 'created_at' => self::daysAgo(3)],
                ['id' => 5, 'file_path' => 'Acta de reunión.docx', 'file_type' => 'DOCX', 'file_size' => 64_000, 'created_at' => self::daysAgo(4)],
            ],
            default => [],
        };

        return $files;
    }

    public static function commentsCount(int $pageId): int
    {
        return count(self::comments($pageId));
    }

    /**
     * Datos para la paleta de comandos.
     *
     * @return array{actions: list<array<string, string>>, pages: list<array<string, string>>, workspaces: list<array<string, string>>}
     */
    public static function palette(string $prefix = '', int $workspaceId = 1): array
    {
        $workspaces = [];

        foreach (self::workspaces() as $workspace) {
            $workspaces[] = [
                'title' => $workspace['name'],
                'meta'  => $workspace['type_label'],
                'icon'  => $workspace['icon'],
                'url'   => nexo_url($prefix, 'workspaces/' . $workspace['id']),
            ];
        }

        $pages = [];

        foreach (self::recentPages(50) as $page) {
            $pages[] = [
                'title' => $page['title'],
                'meta'  => $page['workspace_name'] . ' · ' . nexo_datetime($page['updated_at']),
                'icon'  => nexo_page_icon($page['icon']),
                'url'   => nexo_url($prefix, 'pages/' . $page['id']),
            ];
        }

        return [
            'actions' => [
                ['title' => 'Ir a mi mesa', 'meta' => 'Panel principal', 'icon' => 'home', 'url' => nexo_url($prefix, 'dashboard'), 'shortcut' => 'G D'],
                ['title' => 'Ver espacios', 'meta' => 'Todos los espacios', 'icon' => 'folder', 'url' => nexo_url($prefix, 'workspaces'), 'shortcut' => 'G E'],
                ['title' => 'Abrir tablero', 'meta' => 'Tareas del espacio activo', 'icon' => 'kanban', 'url' => nexo_url($prefix, 'workspaces/' . $workspaceId . '/kanban'), 'shortcut' => 'G K'],
                ['title' => 'Abrir calendario', 'meta' => 'Fechas y entregas', 'icon' => 'calendar', 'url' => nexo_url($prefix, 'workspaces/' . $workspaceId . '/calendar'), 'shortcut' => 'G C'],
                ['title' => 'Nueva página', 'meta' => 'Crear un documento', 'icon' => 'plus', 'url' => nexo_url($prefix, 'pages/nueva') . '?workspace=' . $workspaceId, 'shortcut' => 'N'],
                ['title' => 'Abrir tutor', 'meta' => 'Preguntar al material', 'icon' => 'sparkles', 'url' => '#tutor', 'shortcut' => '⌘ ⇧ T'],
                ['title' => 'Ajustes', 'meta' => 'Perfil y preferencias', 'icon' => 'settings', 'url' => nexo_url($prefix, 'settings'), 'shortcut' => 'G S'],
            ],
            'pages'      => $pages,
            'workspaces' => $workspaces,
        ];
    }

    private static function hydrateWorkspace(array $workspace): array
    {
        $members = self::members();
        $byId    = [];

        foreach ($workspace['member_ids'] as $id) {
            if (isset($members[(int) $id])) {
                $byId[] = $members[(int) $id];
            }
        }

        $workspace['members'] = $byId;

        return $workspace;
    }

    private static function wordCount(array $blocks): int
    {
        $parts = [];
        $ignored = ['style', 'variant', 'language', 'checked', 'code'];
        $collect = static function (mixed $value, string $key = '') use (&$collect, &$parts, $ignored): void {
            if (is_string($value)) {
                if (! in_array($key, $ignored, true)) {
                    $parts[] = $value;
                }

                return;
            }

            if (! is_array($value)) {
                return;
            }

            foreach ($value as $childKey => $child) {
                $collect($child, (string) $childKey);
            }
        };

        foreach ($blocks as $block) {
            $collect($block['content'] ?? []);
        }

        return max(0, str_word_count(implode(' ', $parts), 0, 'áéíóúüñÁÉÍÓÚÜÑ'));
    }

    private static function blocksPageOne(): array
    {
        return [
            self::block(1, 'callout', 0, [
                'title'   => 'Objetivo de la sesión',
                'text'    => 'Entender por qué la solución relajada no siempre sirve y cómo resolver un modelo entero por ramificación y acotación.',
                'variant' => 'info',
            ]),
            self::block(2, 'parrafo', 1, [
                'text'  => 'Del modelo continuo al entero',
                'style' => 'h2',
            ]),
            self::block(3, 'parrafo', 2, [
                'text'  => 'Un modelo de programación lineal puede tener una solución óptima con valores fraccionarios. Cuando las variables representan decisiones indivisibles —personas, máquinas, viajes— esa solución no es válida. El problema se vuelve de programación entera y necesita otra estrategia.',
                'style' => 'p',
            ]),
            self::block(4, 'parrafo', 3, [
                'text'  => 'La relajación lineal (PL) se usa como cota inferior en minimización y como punto de partida para ramificar. Si al resolverla todas las variables enteras ya toman valores enteros, el problema está resuelto.',
                'style' => 'p',
            ]),
            self::block(5, 'checklist', 4, [
                'items' => [
                    ['text' => 'Resolver la relajación lineal', 'checked' => true],
                    ['text' => 'Elegir una variable fraccionaria y ramificar', 'checked' => true],
                    ['text' => 'Acotar las ramas con la mejor solución entera encontrada', 'checked' => false],
                    ['text' => 'Cerrar una rama cuando su cota no pueda mejorar el incumbente', 'checked' => false],
                ],
            ]),
            self::block(6, 'codigo', 5, [
                'language' => 'python',
                'code'     => "from pulp import LpProblem, LpVariable, LpMaximize, LpStatus\n\nmodelo = LpProblem(\"entero\", LpMaximize)\nx = LpVariable(\"x\", lowBound=0, cat=\"Integer\")\ny = LpVariable(\"y\", lowBound=0, cat=\"Integer\")\n\nmodelo += 3 * x + 2 * y\nmodelo += 2 * x + y <= 10\nmodelo += x + 3 * y <= 12\nmodelo.solve()\n\nprint(LpStatus[modelo.status], x.value(), y.value())",
            ]),
            self::block(7, 'tabla', 6, [
                'headers' => ['Método', 'Cuándo usarlo', 'Costo'],
                'rows'    => [
                    ['Ramificación y acotación', 'Modelos enteros pequeños o medianos', 'Exponencial en el peor caso'],
                    ['Planos de corte', 'Relajaciones con brecha grande', 'Requiere solver especializado'],
                    ['Enumeración', 'Pocas variables binarias', 'Crece como 2^n'],
                ],
            ]),
            self::block(8, 'quote', 7, [
                'text' => 'Un modelo no está terminado hasta que sus supuestos se pueden defender.',
                'cite' => 'Apunte de clase · Semana 6',
            ]),
            self::block(9, 'callout', 8, [
                'title'   => 'Antes de la próxima sesión',
                'text'    => 'Traer resuelto el ejercicio 3. Si la cota de una rama coincide con el incumbente, la rama se cierra: no hace falta seguir explorando.',
                'variant' => 'warning',
            ]),
        ];
    }

    private static function blocksPageTwo(): array
    {
        return [
            self::block(1, 'parrafo', 0, ['text' => 'Cómo leer un modelo antes de resolverlo', 'style' => 'h2']),
            self::block(2, 'parrafo', 1, ['text' => 'Antes de escribir código, conviene responder tres preguntas: qué se decide, qué restringe la decisión y qué se quiere optimizar. Si una de las tres no tiene una respuesta clara, el modelo todavía no está listo.', 'style' => 'p']),
            self::block(3, 'tabla', 2, [
                'headers' => ['Parte', 'Pregunta', 'Ejemplo'],
                'rows'    => [
                    ['Variables', '¿Qué decide el modelo?', 'Cuántas unidades producir'],
                    ['Restricciones', '¿Qué no se puede superar?', 'Horas de máquina'],
                    ['Objetivo', '¿Qué se maximiza o minimiza?', 'Margen total'],
                ],
            ]),
            self::block(4, 'codigo', 3, [
                'language' => 'python',
                'code'     => "decisiones = {\"x1\": \"unidades del producto 1\", \"x2\": \"unidades del producto 2\"}\nrestricciones = [\"horas de máquina\", \"demanda mínima\"]\nobjetivo = \"maximizar margen\"\n\nassert all(decisiones.values())\n",
            ]),
        ];
    }

    private static function blocksPageThree(): array
    {
        return [
            self::block(1, 'parrafo', 0, ['text' => 'Ejercicios de la semana', 'style' => 'h2']),
            self::block(2, 'checklist', 1, [
                'items' => [
                    ['text' => 'Ejercicio 1: resolver la relajación y reportar la brecha', 'checked' => true],
                    ['text' => 'Ejercicio 2: dibujar el árbol de ramificación', 'checked' => true],
                    ['text' => 'Ejercicio 3: cerrar las ramas con la cota del incumbente', 'checked' => false],
                ],
            ]),
            self::block(3, 'parrafo', 2, ['text' => 'Para el ejercicio 2, el árbol no necesita ser perfecto: necesita mostrar qué rama se cierra y por qué. La justificación es más importante que el dibujo.', 'style' => 'p']),
            self::block(4, 'callout', 3, [
                'title'   => 'Criterio de entrega',
                'text'    => 'Cada respuesta debe incluir la relajación, la cota y la decisión de ramificación. Sin esos tres elementos, el ejercicio queda incompleto.',
                'variant' => 'info',
            ]),
        ];
    }

    private static function blocksPageFour(): array
    {
        return [
            self::block(1, 'callout', 0, [
                'title'   => 'Documento de referencia',
                'text'    => 'Este sílabus se actualiza al inicio de cada ciclo. Si encuentras una diferencia con el aula virtual, prevalece la versión publicada por la escuela.',
                'variant' => 'info',
            ]),
            self::block(2, 'parrafo', 1, ['text' => 'Evaluación del curso', 'style' => 'h2']),
            self::block(3, 'tabla', 2, [
                'headers' => ['Componente', 'Peso', 'Semana'],
                'rows'    => [
                    ['Prácticas calificadas', '30%', 'Semanas 4 y 9'],
                    ['Laboratorio', '20%', 'Semanas 6 a 14'],
                    ['Proyecto final', '50%', 'Semana 16'],
                ],
            ]),
            self::block(4, 'checklist', 3, [
                'items' => [
                    ['text' => 'Revisar la rúbrica del proyecto final', 'checked' => false],
                    ['text' => 'Confirmar el horario del laboratorio', 'checked' => true],
                    ['text' => 'Descargar el sílabus en PDF', 'checked' => true],
                ],
            ]),
        ];
    }

    private static function blocksPageFive(): array
    {
        return [
            self::block(1, 'parrafo', 0, ['text' => 'NEXO: conocimiento del salón, en un solo lugar', 'style' => 'h2']),
            self::block(2, 'parrafo', 1, ['text' => 'El proyecto busca conectar apuntes, tareas y material de estudio sin obligar al estudiante a saltar entre cinco herramientas. La primera versión se enfoca en tres cosas: páginas colaborativas, tablero de tareas y un tutor que responda con el material del curso.', 'style' => 'p']),
            self::block(3, 'checklist', 2, [
                'items' => [
                    ['text' => 'Cerrar el alcance de la demo', 'checked' => true],
                    ['text' => 'Validar el flujo de páginas y bloques', 'checked' => true],
                    ['text' => 'Conectar el tutor con el backend de IA', 'checked' => false],
                    ['text' => 'Preparar la presentación de la semana 8', 'checked' => false],
                ],
            ]),
            self::block(4, 'callout', 3, [
                'title'   => 'Decisión de producto',
                'text'    => 'No mostrar métricas inventadas. Si un dato no existe, la interfaz muestra un estado vacío honesto y explica qué falta conectar.',
                'variant' => 'success',
            ]),
        ];
    }

    private static function blocksPageSix(): array
    {
        return [
            self::block(1, 'parrafo', 0, ['text' => 'Investigación de operaciones', 'style' => 'h2']),
            self::block(2, 'parrafo', 1, ['text' => 'El curso avanza de modelos determinísticos a decisiones bajo incertidumbre. Estas notas ordenan los temas que entran en la evaluación parcial y los conectan con los laboratorios.', 'style' => 'p']),
            self::block(3, 'tabla', 2, [
                'headers' => ['Tema', 'Modelo', 'Aplicación'],
                'rows'    => [
                    ['Programación lineal', 'Determinístico', 'Mezcla de producción'],
                    ['Transporte', 'Red', 'Distribución'],
                    ['Inventarios', 'Lote económico', 'Reposición'],
                    ['Colas', 'Estocástico', 'Atención al cliente'],
                ],
            ]),
            self::block(4, 'callout', 3, [
                'title'   => 'Para el parcial',
                'text'    => 'No memorizar fórmulas sin entender los supuestos. El examen pide interpretar la solución, no solo calcularla.',
                'variant' => 'warning',
            ]),
        ];
    }

    private static function blocksPageSeven(): array
    {
        return [
            self::block(1, 'callout', 0, [
                'title'   => 'Caso del laboratorio',
                'text'    => 'Una tienda de repuestos vende 4 800 unidades al año. El costo de emitir un pedido es S/ 35 y el costo de mantener una unidad es S/ 4 al año. Calcula el lote económico y el número de pedidos.',
                'variant' => 'info',
            ]),
            self::block(2, 'checklist', 1, [
                'items' => [
                    ['text' => 'Calcular el lote económico (EOQ)', 'checked' => true],
                    ['text' => 'Calcular el número esperado de pedidos', 'checked' => false],
                    ['text' => 'Interpretar el resultado en el contexto del caso', 'checked' => false],
                ],
            ]),
            self::block(3, 'codigo', 2, [
                'language' => 'python',
                'code'     => "from math import sqrt\n\ndemanda = 4800\ncosto_pedido = 35\ncosto_mantener = 4\n\neoq = sqrt((2 * demanda * costo_pedido) / costo_mantener)\npedidos = demanda / eoq\n\nprint(round(eoq, 2), round(pedidos, 2))",
            ]),
            self::block(4, 'parrafo', 3, ['text' => 'El resultado no termina en el número. Hay que explicar qué implica para el almacén: cuánto pedir, cada cuánto y qué supuestos del modelo no se cumplen en la tienda.', 'style' => 'p']),
        ];
    }

    private static function blocksPageEight(): array
    {
        return [
            self::block(1, 'parrafo', 0, ['text' => 'Ruta de aprendizaje del club', 'style' => 'h2']),
            self::block(2, 'parrafo', 1, ['text' => 'La ruta está pensada para estudiantes que ya programan y quieren entender cómo se construye un sistema de IA de principio a fin. Cada sesión deja una página con notas, código y una pregunta abierta.', 'style' => 'p']),
            self::block(3, 'checklist', 2, [
                'items' => [
                    ['text' => 'Sesión 01: fundamentos y entorno', 'checked' => true],
                    ['text' => 'Sesión 02: datos y limpieza', 'checked' => true],
                    ['text' => 'Sesión 03: embeddings y búsqueda', 'checked' => true],
                    ['text' => 'Sesión 04: RAG y evaluación', 'checked' => false],
                    ['text' => 'Sesión 05: proyecto integrador', 'checked' => false],
                ],
            ]),
            self::block(4, 'quote', 3, [
                'text' => 'La IA no reemplaza el criterio: lo pone a prueba con más información.',
                'cite' => 'Club IA · FIIS',
            ]),
        ];
    }

    private static function blocksPageNine(): array
    {
        return [
            self::block(1, 'callout', 0, [
                'title'   => 'Idea central',
                'text'    => 'RAG no es “pegarle un PDF al modelo”. Es recuperar los fragmentos correctos y darle al modelo un contexto verificable para responder.',
                'variant' => 'info',
            ]),
            self::block(2, 'parrafo', 1, ['text' => 'Pipeline mínimo', 'style' => 'h3']),
            self::block(3, 'checklist', 2, [
                'items' => [
                    ['text' => 'Extraer texto y metadatos del material', 'checked' => true],
                    ['text' => 'Dividir en fragmentos con solapamiento', 'checked' => true],
                    ['text' => 'Generar embeddings y guardar el índice', 'checked' => false],
                    ['text' => 'Recuperar, responder y citar la fuente', 'checked' => false],
                ],
            ]),
            self::block(4, 'codigo', 3, [
                'language' => 'python',
                'code'     => "def recuperar(pregunta, indice, k=5):\n    vector = embed(pregunta)\n    resultados = indice.buscar(vector, k=k)\n    return [r for r in resultados if r.score >= 0.72]\n",
            ]),
            self::block(5, 'callout', 4, [
                'title'   => 'Evaluación',
                'text'    => 'Antes de mejorar el prompt, medir recuperación. Un buen modelo con fragmentos irrelevantes sigue dando una mala respuesta.',
                'variant' => 'warning',
            ]),
        ];
    }

    private static function blocksPageTen(): array
    {
        return [
            self::block(1, 'parrafo', 0, ['text' => 'Notas del pipeline', 'style' => 'h3']),
            self::block(2, 'parrafo', 1, ['text' => 'El pipeline del backend extrae texto, lo divide y genera embeddings. El frontend solo consume el contrato de chat; no procesa documentos en el navegador.', 'style' => 'p']),
            self::block(3, 'codigo', 2, [
                'language' => 'php',
                'code'     => "<?php\n\n// El frontend nunca recibe el texto completo del documento.\n// Recibe respuesta + fuentes citables cuando el backend las expone.\n\n\$payload = [\n    'session_id' => \$sessionId,\n    'message'    => \$message,\n    'page_id'    => \$pageId,\n];",
            ]),
        ];
    }

    private static function block(int $id, string $type, int $order, array $content): array
    {
        return [
            'id'          => $id,
            'type'        => $type,
            'content'     => $content,
            'order_index' => $order,
        ];
    }

    private static function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s');
    }

    private static function today(): string
    {
        return (new DateTimeImmutable('today', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d');
    }

    private static function dateIn(int $days): string
    {
        return (new DateTimeImmutable('today', new DateTimeZone(self::TIMEZONE)))
            ->modify(($days >= 0 ? '+' : '') . $days . ' days')
            ->format('Y-m-d');
    }

    private static function daysAgo(int $days): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE)))
            ->modify('-' . $days . ' days')
            ->format('Y-m-d H:i:s');
    }

    private static function hoursAgo(int $hours): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE)))
            ->modify('-' . $hours . ' hours')
            ->format('Y-m-d H:i:s');
    }
}
