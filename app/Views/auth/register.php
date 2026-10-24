<?= $this->extend('layouts/auth') ?>

<?= $this->section('title') ?>Crear cuenta<?= $this->endSection() ?>

<?= $this->section('main') ?>
<?php
$config    = config('Auth');
$isPreview = ! empty($isPreview);
$errors    = session('errors');
$error     = session('error');
?>
<div class="nx-auth__form-wrap">
    <div class="nx-auth__form-head">
        <span class="nx-auth__eyebrow">Primera vez</span>
        <h2 class="nx-auth__title">Empieza con tu salón.</h2>
        <p class="nx-auth__desc">Crea tu cuenta y únete a los espacios de tu escuela, club o proyecto.</p>
    </div>

    <?php if ($isPreview): ?>
        <div class="nx-alert nx-alert--warning" style="margin-bottom: 20px;">
            <?= nexo_icon('info') ?>
            <div class="nx-alert__body">
                <span class="nx-alert__title">Vista de diseño</span>
                El formulario no crea cuentas reales en la demo.
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
        action="<?= $isPreview ? '#' : url_to('register') ?>"
        method="post"
        <?= $isPreview ? 'data-preview-form data-demo-redirect="' . esc(base_url('preview/dashboard'), 'attr') . '"' : '' ?>
    >
        <?= csrf_field() ?>

        <div class="nx-field">
            <label class="nx-label" for="name">Nombre completo</label>
            <input
                class="nx-input nx-input--lg"
                id="name"
                name="name"
                type="text"
                autocomplete="name"
                placeholder="Sebastián Rabanal"
                value="<?= esc(old('name') ?? '') ?>"
                maxlength="150"
                required
                autofocus
            >
        </div>

        <div class="nx-field">
            <label class="nx-label" for="username">
                Nombre de usuario
                <span class="nx-label__hint">Así te verá tu equipo</span>
            </label>
            <input
                class="nx-input nx-input--lg"
                id="username"
                name="username"
                type="text"
                autocomplete="username"
                placeholder="sebastian.rabanal"
                value="<?= esc(old('username') ?? '') ?>"
                minlength="3"
                maxlength="30"
                required
            >
        </div>

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
            >
            <span class="nx-hint">Debes registrarte con tu correo @unac.edu.pe.</span>
        </div>

        <div class="nx-field">
            <label class="nx-label" for="password">Contraseña</label>
            <div class="nx-auth__password">
                <input
                    class="nx-input nx-input--lg"
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    placeholder="Mínimo 8 caracteres"
                    minlength="8"
                    required
                    data-password-input
                    data-password-strength-input
                >
                <button class="nx-auth__password-toggle" type="button" data-password-toggle aria-label="Mostrar contraseña" aria-pressed="false">
                    <?= nexo_icon('eye') ?>
                </button>
            </div>
            <div class="nx-auth__strength" data-password-strength data-score="0" aria-hidden="true">
                <span></span><span></span><span></span><span></span>
            </div>
            <span class="nx-hint" data-password-strength-label>Usa una frase larga o una combinación de letras, números y símbolos.</span>
            <span class="nx-auth__caps" data-caps-warning>Bloq Mayús está activado.</span>
        </div>

        <div class="nx-field">
            <label class="nx-label" for="password_confirm">Confirma la contraseña</label>
            <input
                class="nx-input nx-input--lg"
                id="password_confirm"
                name="password_confirm"
                type="password"
                autocomplete="new-password"
                placeholder="Repite la contraseña"
                required
            >
        </div>

        <div class="nx-auth__note">
            <?= nexo_icon('info') ?>
            <span>Tu cuenta empieza como estudiante. Un docente o administrador puede darte acceso a un espacio después.</span>
        </div>

        <button class="nx-btn nx-btn--primary nx-auth__submit" type="submit">
            Crear cuenta
            <?= nexo_icon('arrow-right') ?>
        </button>
    </form>

    <?php if ($isPreview): ?>
        <div class="nx-divider nx-auth__divider">o</div>
        <a class="nx-btn nx-btn--secondary nx-btn--block" href="<?= base_url('preview/dashboard') ?>">
            Explorar la demo
            <?= nexo_icon('arrow-right') ?>
        </a>
    <?php endif ?>

    <p class="nx-auth__foot">
        ¿Ya tienes cuenta?
        <a href="<?= $isPreview ? base_url('preview/login') : url_to('login') ?>">Entra a NEXO</a>
    </p>

    <p class="nx-auth__legal">
        Al crear tu cuenta aceptas las normas de uso de NEXO UNAC. No compartimos tus documentos con terceros fuera de los espacios a los que perteneces.
    </p>
</div>
<?= $this->endSection() ?>
