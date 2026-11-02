import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Discussion from 'flarum/common/models/Discussion';
import Button from 'flarum/common/components/Button';
import Badge from 'flarum/common/components/Badge';
import DiscussionPage from 'flarum/forum/components/DiscussionPage';
import DiscussionControls from 'flarum/forum/utils/DiscussionControls';

export { default as extend } from './extend';

app.initializers.add('huseyinfiliz-author-moderation', () => {
  extend(DiscussionControls, 'moderationControls', function (items, discussion) {
    if (!discussion.attribute<boolean>('canManageAuthorOnly')) return;

    items.add(
      'authorOnly',
      <Button
        icon="fas fa-user-pen"
        onclick={() =>
          discussion.save({ isAuthorOnly: !discussion.attribute<boolean>('isAuthorOnly') }).then(() => {
            if (app.current.matches(DiscussionPage)) return app.current.get('stream').update();
          })
        }
      >
        {app.translator.trans(
          `huseyinfiliz-author-moderation.forum.${discussion.attribute<boolean>('isAuthorOnly') ? 'disable_author_only' : 'enable_author_only'}`
        )}
      </Button>,
      -10
    );
  });

  extend(Discussion.prototype, 'badges', function (badges) {
    if (!this.attribute<boolean>('authorOnlyActive')) return;

    badges.add(
      'authorOnly',
      <Badge type="authorOnly" icon="fas fa-user-pen" label={app.translator.trans('huseyinfiliz-author-moderation.forum.author_only_badge')} />
    );
  });
});
