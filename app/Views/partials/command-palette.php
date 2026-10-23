<?php
/**
 * Paleta de comandos.
 *
 * @var array $palette
 */
$groups = [
    'Acciones'  => $palette['actions'] ?? [],
    'Páginas'   => $palette['pages'] ?? [],
    'Espacios'  => $palette['workspaces'] ?? [],
];
?>
<div
    class="nx-palette"
    id="nx-palette"
    role="dialog"
    aria-modal="true"
    aria-labelledby="nx-palette-label"
    hidden
>
    <button class="nx-palette__backdrop" type="button" data-palette-close aria-label="Cerrar búsqueda"></button>

    <div class="nx-palette__panel">
        <div class="nx-palette__search">
            <?= nexo_icon('search') ?>
            <label class="nx-sr-only" for="nx-palette-input" id="nx-palette-label">Buscar páginas o ejecutar una acción</label>
            <input
                class="nx-palette__input"
                id="nx-palette-input"
                type="search"
                placeholder="¿A dónde quieres ir?"
                autocomplete="off"
                spellcheck="false"
                role="combobox"
                aria-expanded="false"
                aria-controls="nx-palette-results"
                aria-autocomplete="list"
                data-palette-input
            >
            <span class="nx-kbd nx-palette__esc">Esc</span>
        </div>

        <div class="nx-palette__results nx-scroll" id="nx-palette-results" role="listbox" aria-label="Resultados" data-palette-results>
            <?php foreach ($groups as $label => $items): ?>
                <?php if ($items === []): ?>
                    <?php continue ?>
                <?php endif ?>
                <div class="nx-palette__group" data-palette-group>
                    <div class="nx-palette__group-label"><?= esc($label) ?></div>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $search = mb_strtolower(($item['title'] ?? '') . ' ' . ($item['meta'] ?? '') . ' ' . $label);
                        $action = ($item['url'] ?? '') === '#tutor' ? 'tutor' : '';
                        ?>
                        <button
                            class="nx-palette__item"
                            type="button"
                            role="option"
                            aria-selected="false"
                            data-palette-item
                            data-search="<?= esc($search, 'attr') ?>"
                            data-url="<?= esc($item['url'] ?? '#', 'attr') ?>"
                            data-shortcut="<?= esc($item['shortcut'] ?? '', 'attr') ?>"
                            <?= $action !== '' ? 'data-action="' . esc($action, 'attr') . '"' : '' ?>
                        >
                            <?= nexo_icon($item['icon'] ?? 'arrow-right') ?>
                            <span class="nx-palette__item-main">
                                <span class="nx-palette__item-title"><?= esc($item['title'] ?? '') ?></span>
                                <span class="nx-palette__item-meta"><?= esc($item['meta'] ?? '') ?></span>
                            </span>
                            <?php if (! empty($item['shortcut'])): ?>
                                <span class="nx-kbd nx-palette__item-shortcut"><?= esc($item['shortcut']) ?></span>
                            <?php endif ?>
                        </button>
                    <?php endforeach ?>
                </div>
            <?php endforeach ?>

            <div class="nx-palette__empty" data-palette-empty hidden>
                <?= nexo_icon('search') ?>
                <p>No encontramos nada con ese nombre.</p>
            </div>
        </div>

        <div class="nx-palette__foot">
            <span><span class="nx-kbd">↑</span><span class="nx-kbd">↓</span> navegar</span>
            <span><span class="nx-kbd">Enter</span> abrir</span>
            <span><span class="nx-kbd">Esc</span> cerrar</span>
        </div>
    </div>
</div>
