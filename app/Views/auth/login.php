<?= $this->extend('layouts/auth') ?>

<?= $this->section('title') ?>Entrar<?= $this->endSection() ?>

<?= $this->section('main') ?>
<?php
$config    = config('Auth');
$isPreview = ! empty($isPreview);
$errors    = session('errors');
$error     = session('error');
?>
<div class="nx-auth__form-wrap">
    <div class="nx-auth__form-head">
        <span class="nx-auth__eyebrow">Acceso</span>
        <h2 class="nx-auth__title">Vuelve a tu mesa.</h2>
        <p class="nx-auth__desc">Tus apuntes, tareas y el material del salón te están esperando.</p>
    </div>

    <?php if (! $isPreview && filter_var(env('NEXO_DEMO_LOGIN', 'true'), FILTER_VALIDATE_BOOLEAN)): ?>
        <div class="nx-demo-login" data-demo-login>
            <div class="nx-demo-login__head">
                <span class="nx-badge nx-badge--gold">Piloto</span>
                <strong>Cuenta de demostración</strong>
            </div>
            <p>Para revisar el piloto sin crear datos: <code>demo@unac.edu.pe</code> · <code>NexoDemo2026!</code></p>
            <button class="nx-btn nx-btn--secondary nx-btn--sm" type="button" data-demo-fill>
                Rellenar credenciales
            </button>
        </div>
    <?php endif ?>

    <?php if ($isPreview): ?>
        <div class="nx-alert nx-alert--warning" style="margin-bottom: 20px;">
            <?= nexo_icon('info') ?>
            <div class="nx-alert__body">
                <span class="nx-alert__title">Vista de diseño</span>
                No se validan credenciales. Al continuar entrarás a la demo con datos de ejemplo.
            </div>
        </div>
    <?php endif ?>

    <?php if ($error !== null): ?>
        <div class="nx-alert nx-alert--danger" role="alert" style="margin-bottom: 20px;">
            <?= nexo_icon('alert-circle') ?>
            <div class="nx-alert__body"><?= esc($error) ?></div>
        </div>
    <?php elseif (is_array($errors) && $errors !== []): ?>
        <div class="nx-alert nx-alert--danger" role="alert" style="margin-bottom: 20px;">
            <?= nexo_icon('alert-circle') ?>
            <div class="nx-alert__body">
                <span class="nx-alert__title">Revisa los datos</span>
                <?php foreach ($errors as $message): ?>
                    <div><?= esc($message) ?></div>
                <?php endforeach ?>
            </div>
        </div>
    <?php endif ?>

    <form
        class="nx-auth__form"
        action="<?= $isPreview ? '#' : url_to('login') ?>"
        method="post"
        <?= $isPreview ? 'data-preview-form data-demo-redirect="' . esc(base_url('preview/dashboard'), 'attr') . '"' : '' ?>
    >
        <?= csrf_field() ?>

        <div class="nx-field">
            <label class="nx-label" for="email">Correo institucional</label>
            <input
                class="nx-input nx-input--lg"
                id="email"
                name="email"
                type="email"
                inputmode="email"
                autocomplete="email"
                placeholder="nombre@unac.edu.pe"
                value="<?= esc(old('email') ?? '') ?>"
                required
                autofocus
            >
        </div>

        <div class="nx-field">
            <div class="nx-label">
                <label for="password">Contraseña</label>
                <button class="nx-link-btn" type="button" data-auth-recovery style="font-size: 11px;">Recuperar acceso</button>
            </div>
            <div class="nx-auth__password">
                <input
                    class="nx-input nx-input--lg"
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    placeholder="Tu contraseña"
                    required
                    data-password-input
                >
                <button class="nx-auth__password-toggle" type="button" data-password-toggle aria-label="Mostrar contraseña" aria-pressed="false">
                    <?= nexo_icon('eye') ?>
                </button>
            </div>
            <span class="nx-auth__caps" data-caps-warning>Bloq Mayús está activado.</span>
        </div>

        <div class="nx-auth__row">
            <label class="nx-check">
                <input type="checkbox" name="remember" value="1" <?= old('remember') ? 'checked' : '' ?>>
                <span>Recordarme en este equipo</span>
            </label>
            <span class="nx-muted-2" style="font-size: 10px;">Sesión segura</span>
        </div>

        <button class="nx-btn nx-btn--primary nx-auth__submit" type="submit">
            Entrar a NEXO
            <?= nexo_icon('arrow-right') ?>
        </button>
    </form>

    <?php if ($isPreview): ?>
        <div class="nx-divider nx-auth__divider">o</div>
        <a class="nx-btn nx-btn--secondary nx-btn--block" href="<?= base_url('preview/dashboard') ?>">
            Explorar sin iniciar sesión
            <?= nexo_icon('arrow-right') ?>
        </a>
    <?php elseif ($config->allowRegistration): ?>
        <div class="nx-divider nx-auth__divider">o</div>
        <a class="nx-btn nx-btn--secondary nx-btn--block" href="<?= url_to('register') ?>">
            Crear una cuenta nueva
            <?= nexo_icon('arrow-right') ?>
        </a>
    <?php endif ?>

    <p class="nx-auth__foot">
        <?php if ($isPreview): ?>
            <a href="<?= base_url('preview/ui-kit') ?>">Ver el sistema de diseño</a>
        <?php else: ?>
            ¿Problemas para entrar? Escribe a <a href="mailto:soporte@unac.edu.pe">soporte@unac.edu.pe</a>
        <?php endif ?>
    </p>

    <p class="nx-auth__legal">
        Al entrar aceptas las normas de uso de NEXO UNAC. Tus documentos pertenecen a tu espacio y a las personas con quienes los compartes.
    </p>
</div>
<?= $this->endSection() ?>
