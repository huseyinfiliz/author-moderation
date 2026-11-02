import app from 'flarum/admin/app';
import AuthorModerationPage from './components/AuthorModerationPage';
import isExtensionEnabled from 'flarum/admin/utils/isExtensionEnabled';

export { default as extend } from './extend';

app.initializers.add('huseyinfiliz-author-moderation', () => {
  const registry = app.registry.for('huseyinfiliz-author-moderation');
  registry.registerPage(AuthorModerationPage);

  [
    ['discussion.authorModerationEditPosts', 'edit_posts', 'fas fa-pen'],
    ['discussion.authorModerationHidePosts', 'hide_posts', 'fas fa-trash'],
    ['discussion.authorModerationRestorePosts', 'restore_posts', 'fas fa-reply'],
    ['discussion.authorModerationRename', 'rename', 'fas fa-heading'],
    ['discussion.authorModerationHide', 'hide_discussion', 'fas fa-trash'],
    ['discussion.authorModerationRestore', 'restore_discussion', 'fas fa-reply'],
    ['huseyinfiliz-author-moderation.exempt', 'exempt', 'fas fa-shield-alt'],
    ['discussion.authorModerationJournal', 'author_only', 'fas fa-user-pen'],
    ['discussion.manageAuthorOnly', 'manage_author_only', 'fas fa-user-shield'],
    ['huseyinfiliz-author-moderation.replyInAuthorOnly', 'reply_author_only', 'fas fa-comment'],
  ].forEach(([permission, label, icon]) => {
    registry.registerPermission(
      { permission, label: app.translator.trans(`huseyinfiliz-author-moderation.admin.permissions.${label}`), icon },
      'moderate'
    );
  });

  if (isExtensionEnabled('flarum-lock')) {
    registry.registerPermission(
      {
        permission: 'discussion.authorModerationLock',
        label: app.translator.trans('huseyinfiliz-author-moderation.admin.permissions.lock'),
        icon: 'fas fa-lock',
      },
      'moderate'
    );
  }
});
