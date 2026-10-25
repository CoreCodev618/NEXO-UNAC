<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Entities\User;

final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call('FacultySeeder');
        $this->call('WorkspaceSeeder');

        $faculty = $this->db->table('faculties')
            ->where('short_code', 'FIIS')
            ->get()
            ->getRow();

        if ($faculty === null) {
            throw new \RuntimeException('Falta FIIS; corre FacultySeeder.');
        }

        $facultyId = (int) $faculty->id;

        $demoId = $this->ensureUser('demo', 'demo@unac.edu.pe', 'Sebastián Rabanal', 'admin', $facultyId);
        $anaId  = $this->ensureUser('ana.torres', 'ana.torres@unac.edu.pe', 'Ana Torres', 'docente', $facultyId);
        $luisId = $this->ensureUser('luis.quispe', 'luis.quispe@unac.edu.pe', 'Luis Quispe', 'alumno', $facultyId);
        $camiId = $this->ensureUser('camila.rojas', 'camila.rojas@unac.edu.pe', 'Camila Rojas', 'alumno', $facultyId);

        $workspaces = [
            'EP Sistemas'    => ['EP-Sistemas', $demoId],
            'EP Industrial'  => ['EP-Industrial', $demoId],
            'Club IA-FIIS'   => ['club', $demoId],
        ];

        $workspaceIds = [];

        foreach ($workspaces as $name => [$type, $ownerId]) {
            $row = $this->db->table('workspaces')
                ->where('name', $name)
                ->where('type', $type)
                ->get()
                ->getRow();

            if ($row === null) {
                $this->db->table('workspaces')->insert([
                    'faculty_id' => $facultyId,
                    'owner_id'   => $ownerId,
                    'name'       => $name,
                    'type'       => $type,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $workspaceId = (int) $this->db->insertID();
            } else {
                $workspaceId = (int) $row->id;
                $this->db->table('workspaces')->where('id', $workspaceId)->update([
                    'faculty_id' => $facultyId,
                    'owner_id'   => $ownerId,
                ]);
            }

            $workspaceIds[$name] = $workspaceId;
        }

        $this->ensureMembership($workspaceIds['EP Sistemas'], $demoId, 'owner');
        $this->ensureMembership($workspaceIds['EP Sistemas'], $anaId, 'editor');
        $this->ensureMembership($workspaceIds['EP Sistemas'], $luisId, 'editor');
        $this->ensureMembership($workspaceIds['EP Sistemas'], $camiId, 'viewer');

        $this->ensureMembership($workspaceIds['EP Industrial'], $demoId, 'owner');
        $this->ensureMembership($workspaceIds['EP Industrial'], $anaId, 'editor');
        $this->ensureMembership($workspaceIds['EP Industrial'], $luisId, 'viewer');

        $this->ensureMembership($workspaceIds['Club IA-FIIS'], $demoId, 'owner');
        $this->ensureMembership($workspaceIds['Club IA-FIIS'], $anaId, 'editor');
        $this->ensureMembership($workspaceIds['Club IA-FIIS'], $camiId, 'viewer');

        $semana6 = $this->ensurePage($workspaceIds['EP Sistemas'], $demoId, 'Semana 6 · Guía de programación entera', 'book-open', 'wine');
        $pc1     = $this->ensurePage($workspaceIds['EP Sistemas'], $demoId, 'PC1 resuelta', 'file-text', 'gold');
        $io4     = $this->ensurePage($workspaceIds['EP Industrial'], $demoId, 'Investigación de operaciones · Semana 4', 'building', 'gold');
        $ruta    = $this->ensurePage($workspaceIds['Club IA-FIIS'], $demoId, 'Ruta de aprendizaje', 'sparkles', 'sage');

        $this->ensureBlocks($semana6, [
            ['callout', ['variant' => 'info', 'title' => 'Objetivo de la sesión', 'text' => 'Entender por qué la solución relajada no siempre sirve y cómo resolver un modelo entero por ramificación y acotación.']],
            ['parrafo', ['style' => 'h2', 'text' => 'Relajación y acotación']],
            ['parrafo', ['style' => 'p', 'text' => 'La relajación lineal da una cota; la ramificación y acotación la ajusta hasta encontrar la solución entera.']],
            ['codigo', ['language' => 'php', 'code' => "<?php\n\$solution = branchAndBound(\$model);\necho \$solution;"]],
            ['checklist', ['items' => [
                ['text' => 'Leer la guía de la semana', 'checked' => true],
                ['text' => 'Resolver el ejercicio 3', 'checked' => false],
                ['text' => 'Comparar con la PC1', 'checked' => false],
            ]]],
        ]);

        $this->ensureBlocks($pc1, [
            ['parrafo', ['style' => 'p', 'text' => 'Solución paso a paso de la primera práctica calificada.']],
            ['tabla', ['headers' => ['Iteración', 'Cota', 'Solución'], 'rows' => [['1', '12.5', 'x=2.5'], ['2', '13.0', 'x=2'], ['3', '13.0', 'óptimo']]]],
            ['codigo', ['language' => 'python', 'code' => "def branch_and_bound(model):\n    return solve(model)"]],
        ]);

        $this->ensureBlocks($io4, [
            ['parrafo', ['style' => 'p', 'text' => 'Modelado de colas y tiempos de espera en un sistema de atención.']],
            ['callout', ['variant' => 'warning', 'title' => 'Antes de la práctica', 'text' => 'Revisar la fórmula de Little y los supuestos de Poisson.']],
            ['checklist', ['items' => [
                ['text' => 'Leer el caso de la empresa', 'checked' => true],
                ['text' => 'Calcular L, Lq, W y Wq', 'checked' => false],
            ]]],
        ]);

        $this->ensureBlocks($ruta, [
            ['parrafo', ['style' => 'p', 'text' => 'De Python básico a un primer modelo de lenguaje: la ruta del club para este ciclo.']],
            ['checklist', ['items' => [
                ['text' => 'Python y Git', 'checked' => true],
                ['text' => 'Datos y estadística', 'checked' => true],
                ['text' => 'Embeddings y RAG', 'checked' => false],
                ['text' => 'Proyecto final', 'checked' => false],
            ]]],
            ['quote', ['text' => 'La mejor forma de aprender IA es construir algo que te sirva.', 'cite' => 'Club IA-FIIS']],
        ]);

        $this->ensureTasks($workspaceIds['EP Sistemas'], [
            ['Terminar la guía de programación entera', 'doing', 'alta', '+1 day', $luisId, $semana6],
            ['Subir los apuntes de la Semana 6', 'todo', 'media', '+3 days', $demoId, $semana6],
            ['Revisar la PC1 resuelta', 'done', 'baja', '-2 days', $anaId, $pc1],
        ]);

        $this->ensureTasks($workspaceIds['EP Industrial'], [
            ['Preparar el laboratorio de IO', 'todo', 'alta', '+4 days', $demoId, $io4],
        ]);

        $this->ensureTasks($workspaceIds['Club IA-FIIS'], [
            ['Publicar la ruta de aprendizaje', 'done', 'baja', '-5 days', $demoId, $ruta],
            ['Documentar el pipeline de embeddings', 'todo', 'media', '+6 days', $luisId, $ruta],
        ]);

        // Contenido colaborativo y material citables por el tutor. Todo es
        // sintético y está marcado como demo; no se usan datos personales.
        $this->ensureTags($semana6, ['Programación entera', 'Semana 6', 'PC1']);
        $this->ensureTags($pc1, ['PC1', 'Solucionario']);
        $this->ensureTags($io4, ['Investigación de operaciones', 'Colas']);
        $this->ensureTags($ruta, ['RAG', 'Ruta de aprendizaje']);

        $this->ensureComments($semana6, [
            [$anaId, 'Para la PC1 revisen el ejemplo de relajación y poda. La idea es comparar la cota con la mejor solución entera.'],
            [$luisId, '¿La cota de la relajación siempre es alcanzable?'],
        ]);
        $this->ensureComments($pc1, [
            [$anaId, 'El solucionario es una guía; intenten resolver cada iteración antes de mirar la tabla.'],
        ]);
        $this->ensureComments($io4, [
            [$luisId, 'Agregué una nota sobre la fórmula de Little para el laboratorio.'],
        ]);
        $this->ensureComments($ruta, [
            [$camiId, 'La ruta me ayudó a ordenar los temas. ¿El proyecto final puede ser un RAG pequeño?'],
        ]);

        $this->ensureAttachment(
            $semana6,
            $demoId,
            'guia-semana-6.txt',
            "Guía sintética de la Semana 6\n\n"
            . "Tema: programación entera.\n"
            . "1. Relajación lineal y cota.\n"
            . "2. Ramificación y acotación.\n"
            . "3. Poda de ramas no prometedoras.\n"
            . "4. Comparación con la solución de la PC1.\n"
        );

        $this->ensureDocuments($semana6, $workspaceIds['EP Sistemas'], $demoId, [
            'title' => 'Guía de programación entera · Semana 6',
            'topic' => 'Programación entera',
            'class_date' => date('Y-m-d', strtotime('-3 days')),
            'chunks' => [
                'La relajación lineal de un modelo de programación entera permite que las variables tomen valores fraccionarios. Su valor óptimo es una cota para el problema entero: en maximización, una cota superior.',
                'En ramificación y acotación se resuelve primero la relajación. Si la solución es entera, se guarda como candidata. Si no, se elige una variable fraccionaria y se crean dos ramas con restricciones x ≤ piso y x ≥ techo.',
                'La poda compara la cota de una rama con la mejor solución entera encontrada. Si la cota no puede mejorarla, la rama se descarta. Esto evita explorar todo el árbol.',
                'La PC1 pide comparar la solución relajada con la solución entera. No basta con redondear: un redondeo puede violar restricciones o dejar de ser óptimo.',
            ],
        ]);

        $this->ensureDocuments($pc1, $workspaceIds['EP Sistemas'], $demoId, [
            'title' => 'PC1 resuelta · Programación entera',
            'topic' => 'PC1',
            'class_date' => date('Y-m-d', strtotime('-1 day')),
            'chunks' => [
                'La primera práctica calificada tiene cuatro preguntas. La pregunta 3 se resuelve con ramificación y acotación y exige mostrar el árbol de decisiones.',
                'Para justificar la optimalidad hay que indicar la mejor solución entera y demostrar que ninguna rama pendiente puede superarla.',
                'Un error frecuente es redondear la solución relajada sin comprobar la factibilidad. Siempre se debe verificar cada restricción.',
            ],
        ]);

        $this->ensureDocuments($io4, $workspaceIds['EP Industrial'], $anaId, [
            'title' => 'Investigación de operaciones · Colas',
            'topic' => 'Teoría de colas',
            'class_date' => date('Y-m-d', strtotime('-5 days')),
            'chunks' => [
                'La ley de Little relaciona el número promedio de clientes en el sistema L, la tasa de llegadas λ y el tiempo promedio en el sistema W: L = λW.',
                'Para la cola, Lq = λWq. El tiempo total se relaciona con el de espera mediante W = Wq + 1/μ, donde 1/μ es el tiempo medio de servicio.',
                'En un sistema M/M/1 se supone llegadas Poisson, servicio exponencial y un solo servidor. Antes de aplicar fórmulas hay que verificar esos supuestos.',
            ],
        ]);

        $this->ensureDocuments($ruta, $workspaceIds['Club IA-FIIS'], $camiId, [
            'title' => 'Ruta de aprendizaje · RAG',
            'topic' => 'RAG',
            'class_date' => date('Y-m-d', strtotime('-2 days')),
            'chunks' => [
                'Un embedding representa un texto como un vector. Textos con significado parecido quedan cerca en el espacio vectorial.',
                'RAG combina recuperación y generación: primero se buscan fragmentos relevantes y luego el modelo responde usando esos fragmentos como contexto.',
                'Una respuesta RAG debe citar las fuentes recuperadas. Si no hay evidencia suficiente, el tutor debe decirlo en lugar de inventar.',
            ],
        ]);
    }

    /**
     * @param list<string> $tags
     */
    private function ensureTags(int $pageId, array $tags): void
    {
        $exists = $this->db->table('page_tags')->where('page_id', $pageId)->countAllResults();
        if ($exists > 0) {
            return;
        }

        foreach ($tags as $name) {
            $tag = $this->db->table('tags')->where('name', $name)->get()->getRow();

            if ($tag === null) {
                $this->db->table('tags')->insert([
                    'name'       => $name,
                    'color'      => 'wine',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $tagId = (int) $this->db->insertID();
            } else {
                $tagId = (int) $tag->id;
            }

            $this->db->table('page_tags')->insert([
                'page_id' => $pageId,
                'tag_id'  => $tagId,
            ]);
        }
    }

    /**
     * @param list<array{0:int,1:string}> $comments
     */
    private function ensureComments(int $pageId, array $comments): void
    {
        $exists = $this->db->table('comments')->where('page_id', $pageId)->countAllResults();
        if ($exists > 0) {
            return;
        }

        foreach ($comments as [$userId, $content]) {
            $this->db->table('comments')->insert([
                'page_id'    => $pageId,
                'user_id'    => $userId,
                'content'    => $content,
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . random_int(1, 48) . ' hours')),
            ]);
        }
    }

    private function ensureAttachment(int $pageId, int $userId, string $name, string $content): void
    {
        $exists = $this->db->table('attachments')->where('page_id', $pageId)->countAllResults();
        if ($exists > 0) {
            return;
        }

        $folder = WRITEPATH . 'uploads/attachments/' . date('Y/m');
        if (! is_dir($folder)) {
            mkdir($folder, 0o775, true);
        }

        $stored = 'demo-' . bin2hex(random_bytes(8)) . '.txt';
        file_put_contents($folder . '/' . $stored, $content);

        $this->db->table('attachments')->insert([
            'page_id'    => $pageId,
            'user_id'    => $userId,
            'file_path'  => 'attachments/' . date('Y/m') . '/' . $stored,
            'file_name'  => $name,
            'file_type'  => 'text/plain',
            'file_size'  => strlen($content),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array{title:string, topic:string, class_date:string, chunks:list<string>} $doc
     */
    private function ensureDocuments(int $pageId, int $workspaceId, int $userId, array $doc): void
    {
        $exists = $this->db->table('documents')
            ->where('page_id', $pageId)
            ->where('title', $doc['title'])
            ->countAllResults();

        if ($exists > 0) {
            return;
        }

        $folder = WRITEPATH . 'uploads/rag';
        if (! is_dir($folder)) {
            mkdir($folder, 0o775, true);
        }

        $stored   = 'demo-' . bin2hex(random_bytes(8)) . '.txt';
        $relative = 'writable/uploads/rag/' . $stored;
        $content  = implode("\n\n", $doc['chunks']);
        file_put_contents($folder . '/' . $stored, $content);

        $this->db->table('documents')->insert([
            'page_id'      => $pageId,
            'workspace_id' => $workspaceId,
            'title'        => $doc['title'],
            'file_path'    => $relative,
            'source_key'   => $relative,
            'mime_type'    => 'text/plain',
            'size_bytes'   => strlen($content),
            'curso_id'     => 1,
            'class_date'   => $doc['class_date'],
            'topic'        => $doc['topic'],
            'created_by'   => $userId,
            'indexed_v2'   => true,
            'indexed_at'   => date('Y-m-d H:i:s'),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $docId = (int) $this->db->insertID();

        foreach ($doc['chunks'] as $index => $chunk) {
            $this->db->table('document_chunks')->insert([
                'document_id'  => $docId,
                'chunk_index'  => $index,
                'content'      => $chunk,
                'token_count'  => max(1, (int) round(str_word_count($chunk) * 1.3)),
                'embedding_id' => 'local-demo-' . $docId . '-' . $index,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function ensureUser(string $username, string $email, string $name, string $group, int $facultyId): int
    {
        $provider = auth()->getProvider();
        $user     = $provider->findByCredentials(['username' => $username]);

        if ($user === null) {
            $user = new User([
                'username' => $username,
                'email'    => $email,
                'password' => 'NexoDemo2026!',
            ]);

            $provider->save($user);
            $user = $provider->findByCredentials(['username' => $username]);
        }

        if ($user === null) {
            throw new \RuntimeException('No se pudo crear el usuario ' . $username);
        }

        $user->name       = $name;
        $user->faculty_id = $facultyId;
        $provider->save($user);

        if (! $user->inGroup($group)) {
            $user->addGroup($group);
        }

        return (int) $user->id;
    }

    private function ensureMembership(int $workspaceId, int $userId, string $role): void
    {
        $exists = $this->db->table('workspace_members')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->countAllResults();

        if ($exists === 0) {
            $this->db->table('workspace_members')->insert([
                'workspace_id' => $workspaceId,
                'user_id'      => $userId,
                'role'         => $role,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function ensurePage(int $workspaceId, int $userId, string $title, string $icon, string $cover): int
    {
        $row = $this->db->table('pages')
            ->where('workspace_id', $workspaceId)
            ->where('title', $title)
            ->get()
            ->getRow();

        if ($row !== null) {
            return (int) $row->id;
        }

        $this->db->table('pages')->insert([
            'workspace_id' => $workspaceId,
            'parent_id'    => null,
            'created_by'   => $userId,
            'title'        => $title,
            'icon'         => $icon,
            'cover'        => $cover,
            'order_index'  => 0,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * @param list<array{0:string,1:array<string,mixed>}> $blocks
     */
    private function ensureBlocks(int $pageId, array $blocks): void
    {
        $exists = $this->db->table('blocks')->where('page_id', $pageId)->countAllResults();

        if ($exists > 0) {
            return;
        }

        foreach ($blocks as $index => [$type, $content]) {
            $this->db->table('blocks')->insert([
                'page_id'     => $pageId,
                'type'        => $type,
                'content'     => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'order_index' => $index,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * @param list<array{0:string,1:string,2:string,3:string,4:int,5:int}> $tasks
     */
    private function ensureTasks(int $workspaceId, array $tasks): void
    {
        $exists = $this->db->table('tasks')->where('workspace_id', $workspaceId)->countAllResults();

        if ($exists > 0) {
            return;
        }

        foreach ($tasks as $index => [$title, $status, $priority, $dueOffset, $assignedTo, $pageId]) {
            $this->db->table('tasks')->insert([
                'workspace_id' => $workspaceId,
                'page_id'      => $pageId,
                'assigned_to'  => $assignedTo,
                'title'        => $title,
                'status'       => $status,
                'priority'     => $priority,
                'order_index'  => $index,
                'due_date'     => date('Y-m-d', strtotime($dueOffset)),
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
