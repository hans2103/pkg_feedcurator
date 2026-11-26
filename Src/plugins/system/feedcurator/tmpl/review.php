<?php

/**
 * @package     HKweb.Feedcurator
 * @subpackage  plg_system_feedcurator
 *
 * The review page. Override it in templates/{template}/html/plg_system_feedcurator/review.php.
 * Every value is plain text and is escaped here; the page works without JavaScript.
 *
 * @copyright   (C) 2026 HKweb
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * @var  \Joomla\CMS\Application\CMSApplicationInterface      $app
 * @var  string                                               $heading
 * @var  list<array{type: string, text: string}>              $messages
 * @var  ?array<string, string>                               $article   title, state, summary, source, link, linkText
 * @var  ?array<string, mixed>                                $form      action, a, u, e, s, actingAs
 * @var  list<array{url: string, text: string}>               $links
 */

declare(strict_types=1);

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

$document = $app->getDocument();

if ($document instanceof HtmlDocument)
{
	$document->getWebAssetManager()->addInlineStyle(
		'.feedcurator-review{max-inline-size:40rem}'
		. '.feedcurator-review__message{padding:.75rem 1rem;border-inline-start:.375rem solid currentcolor;margin-block:1rem}'
		. '.feedcurator-review__actions{display:flex;flex-wrap:wrap;gap:1rem;margin-block:1.5rem}'
		. '.feedcurator-review__button{min-block-size:44px;min-inline-size:44px;padding:.625rem 1.5rem;font:inherit;font-weight:700;'
		. 'border:2px solid currentcolor;border-radius:.25rem;cursor:pointer}'
		. '.feedcurator-review__button:focus-visible{outline:3px solid currentcolor;outline-offset:3px}'
	);
}

$roles = ['error' => 'alert', 'warning' => 'alert'];
?>
<div class="feedcurator-review">
	<h1><?php echo $e($heading); ?></h1>

	<?php foreach ($messages as $message) : ?>
		<div class="feedcurator-review__message feedcurator-review__message--<?php echo $e($message['type']); ?>" role="<?php echo $roles[$message['type']] ?? 'status'; ?>">
			<p><?php echo $e($message['text']); ?></p>
		</div>
	<?php endforeach; ?>

	<?php if ($article !== null) : ?>
		<section class="feedcurator-review__article" aria-labelledby="feedcurator-review-title">
			<h2 id="feedcurator-review-title"><?php echo $e($article['title']); ?></h2>
			<?php if ($article['summary'] !== '') : ?>
				<p><?php echo $e($article['summary']); ?></p>
			<?php endif; ?>
			<?php if ($article['source'] !== '' || $article['link'] !== '') : ?>
				<p class="feedcurator-review__source">
					<?php echo $e($article['source']); ?>
					<?php if ($article['link'] !== '') : ?>
						<?php echo $article['source'] !== '' ? '&mdash;' : ''; ?>
						<a href="<?php echo $e($article['link']); ?>" rel="noopener"><?php echo $e($article['linkText'] !== '' ? $article['linkText'] : $article['link']); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<p><strong><?php echo $e(Text::_('PLG_SYSTEM_FEEDCURATOR_STATE_LABEL')); ?></strong> <?php echo $e($article['state']); ?></p>
		</section>
	<?php endif; ?>

	<?php if ($form !== null) : ?>
		<form method="post" action="<?php echo $e($form['action']); ?>" class="feedcurator-review__form">
			<p><?php echo $e(Text::_('PLG_SYSTEM_FEEDCURATOR_INTRO')); ?></p>
			<p><?php echo $e($form['actingAs']); ?></p>
			<input type="hidden" name="a" value="<?php echo $e($form['a']); ?>">
			<input type="hidden" name="u" value="<?php echo $e($form['u']); ?>">
			<input type="hidden" name="e" value="<?php echo $e($form['e']); ?>">
			<input type="hidden" name="s" value="<?php echo $e($form['s']); ?>">
			<?php echo HTMLHelper::_('form.token'); ?>
			<div class="feedcurator-review__actions">
				<button type="submit" name="action" value="publish" class="feedcurator-review__button feedcurator-review__button--publish">
					<?php echo $e(Text::_('PLG_SYSTEM_FEEDCURATOR_BUTTON_PUBLISH')); ?>
				</button>
				<button type="submit" name="action" value="discard" class="feedcurator-review__button feedcurator-review__button--discard">
					<?php echo $e(Text::_('PLG_SYSTEM_FEEDCURATOR_BUTTON_DISCARD')); ?>
				</button>
			</div>
		</form>
	<?php endif; ?>

	<?php foreach ($links as $link) : ?>
		<p><a href="<?php echo $e($link['url']); ?>"><?php echo $e($link['text']); ?></a></p>
	<?php endforeach; ?>
</div>
