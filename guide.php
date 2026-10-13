<?php
/**
 * The visitor guide.
 *
 * Content lives in data/guide.php; this file only lays it out. The table of
 * contents is built from the same array as the sections, so a new section can
 * never appear in one and not the other.
 */

declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$sections = require __DIR__ . '/data/guide.php';

page_head([
    'nav'   => 'guide',
    'title' => t('guide.title'),
    'desc'  => t('guide.sub'),
]);
?>

<section class="wrap hero compact">
  <span class="pill brand"><?= e(t('nav.guide')) ?></span>
  <h1><?= e(t('guide.title')) ?></h1>
  <p class="lede"><?= e(t('guide.sub')) ?></p>
</section>

<div class="wrap guide-layout">

  <nav class="guide-toc" aria-label="<?= e(t('guide.toc')) ?>">
    <h2><?= e(t('guide.toc')) ?></h2>
    <ol>
      <?php foreach ($sections as $s): ?>
        <li>
          <a href="#g-<?= e((string) $s['slug']) ?>">
            <span aria-hidden="true"><?= e((string) $s['icon']) ?></span>
            <?= e(pick($s, 'title')) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ol>
  </nav>

  <div class="guide-body">
    <?php foreach ($sections as $s): ?>
      <section class="guide-sec" id="g-<?= e((string) $s['slug']) ?>">
        <header>
          <h2><span aria-hidden="true"><?= e((string) $s['icon']) ?></span> <?= e(pick($s, 'title')) ?></h2>
          <p class="lede"><?= e(pick($s, 'lede')) ?></p>
        </header>

        <?php foreach ($s['items'] as $item): ?>
          <div class="guide-item">
            <b><?= e(pick($item, 't')) ?></b>
            <p><?= e(pick($item, 'b')) ?></p>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>

    <div class="tips">
      <h3><?= e(t('guide.note_title')) ?></h3>
      <p><?= e(t('guide.note')) ?></p>
    </div>

    <div class="cta-band">
      <h2><?= e(t('places.title')) ?></h2>
      <p><?= e(t('places.sub')) ?></p>
      <a class="btn primary" href="<?= e(APP_BASE) ?>/places.php"><?= e(t('guide.places_cta')) ?></a>
    </div>
  </div>
</div>

<?php
page_foot();
