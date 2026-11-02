import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import ExtensionPermissionGrid from 'flarum/admin/components/ExtensionPermissionGrid';
import isExtensionEnabled from 'flarum/admin/utils/isExtensionEnabled';
import Button from 'flarum/common/components/Button';
import Form from 'flarum/common/components/Form';
import type Mithril from 'mithril';

const prefix = 'huseyinfiliz-author-moderation';
type Tab = 'moderation' | 'author_only' | 'eligibility' | 'permissions' | 'integrations';

export default class AuthorModerationPage extends ExtensionPage {
  activeTab: Tab = 'moderation';

  sections(vnode: Mithril.VnodeDOM) {
    const items = super.sections(vnode);
    items.remove('permissions');
    return items;
  }

  content() {
    const tabs: [Tab, string][] = [
      ['moderation', 'fas fa-user-shield'],
      ['author_only', 'fas fa-user-pen'],
      ['eligibility', 'fas fa-user-check'],
      ['permissions', 'fas fa-key'],
      ['integrations', 'fas fa-puzzle-piece'],
    ];

    return (
      <div className="AuthorModerationPage">
        <nav className="AuthorModerationPage-tabs" aria-label={app.translator.trans(`${prefix}.admin.navigation`)}>
          {tabs.map(([tab, icon]) => (
            <Button
              key={tab}
              className={`Button ${this.activeTab === tab ? 'active' : ''}`}
              icon={icon}
              aria-pressed={this.activeTab === tab}
              onclick={() => (this.activeTab = tab)}
            >
              {app.translator.trans(`${prefix}.admin.tabs.${tab}`)}
            </Button>
          ))}
        </nav>
        <div className="AuthorModerationPage-content">
          {this.activeTab === 'permissions' ? (
            <div>
              <h3>{app.translator.trans(`${prefix}.admin.tabs.permissions`)}</h3>
              <p className="helpText">{app.translator.trans(`${prefix}.admin.permissions_help`)}</p>
              <div className="AuthorModerationPage-permissions">
                <ExtensionPermissionGrid extensionId={prefix} />
              </div>
            </div>
          ) : this.activeTab === 'integrations' ? (
            this.integrations()
          ) : (
            <Form>
              <h3>{app.translator.trans(`${prefix}.admin.tabs.${this.activeTab}`)}</h3>
              {this.activeTab === 'moderation'
                ? this.moderationSettings()
                : this.activeTab === 'author_only'
                  ? this.authorOnlySettings()
                  : this.eligibilitySettings()}
              <div className="Form-group">{this.submitButton()}</div>
            </Form>
          )}
        </div>
      </div>
    );
  }

  moderationSettings() {
    return [
      <p className="helpText">{app.translator.trans(`${prefix}.admin.moderation_help`)}</p>,
      this.buildSettingComponent({
        setting: `${prefix}.moderation_enabled`,
        type: 'boolean',
        label: app.translator.trans(`${prefix}.admin.settings.moderation_enabled`),
        help: app.translator.trans(`${prefix}.admin.settings.moderation_enabled_help`),
      }),
      this.buildSettingComponent({
        setting: `${prefix}.restore_others`,
        type: 'boolean',
        label: app.translator.trans(`${prefix}.admin.settings.restore_others`),
        help: app.translator.trans(`${prefix}.admin.settings.restore_others_help`),
      }),
      <p className="helpText">{app.translator.trans(`${prefix}.admin.exemption_help`)}</p>,
      <p className="helpText">{app.translator.trans(`${prefix}.admin.lock_help`)}</p>,
    ];
  }

  authorOnlySettings() {
    return [
      <p className="helpText">{app.translator.trans(`${prefix}.admin.author_only_help`)}</p>,
      this.buildSettingComponent({
        setting: `${prefix}.author_only_enabled`,
        type: 'boolean',
        label: app.translator.trans(`${prefix}.admin.settings.author_only_enabled`),
        help: app.translator.trans(`${prefix}.admin.settings.author_only_enabled_help`),
      }),
    ];
  }

  eligibilitySettings() {
    const available = isExtensionEnabled('fof-moderator-warnings');

    return [
      <p className="helpText">{app.translator.trans(`${prefix}.admin.warning_help`)}</p>,
      !available && <p className="AuthorModerationPage-notice">{app.translator.trans(`${prefix}.admin.warnings_unavailable`)}</p>,
      this.buildSettingComponent({
        setting: `${prefix}.warning_limit_enabled`,
        type: 'boolean',
        label: app.translator.trans(`${prefix}.admin.settings.warning_limit_enabled`),
        disabled: !available,
      }),
      this.buildSettingComponent({
        setting: `${prefix}.warning_threshold`,
        type: 'number',
        label: app.translator.trans(`${prefix}.admin.settings.warning_threshold`),
        help: app.translator.trans(`${prefix}.admin.settings.warning_threshold_help`),
        min: 1,
        step: 1,
        required: true,
        disabled: !available || !['1', 'true'].includes(String(this.setting(`${prefix}.warning_limit_enabled`)())),
      }),
    ];
  }

  integrations() {
    return (
      <div>
        <h3>{app.translator.trans(`${prefix}.admin.tabs.integrations`)}</h3>
        {(['fof-moderator-warnings', 'flarum-audit', 'flarum-lock'] as const).map((id) => (
          <div className="AuthorModerationPage-integration" key={id}>
            <strong>
              {{ 'flarum-audit': 'Flarum Audit', 'flarum-lock': 'Flarum Lock', 'fof-moderator-warnings': 'FoF Moderator Warnings' }[id]}
            </strong>
            <span>{app.translator.trans(`${prefix}.admin.${isExtensionEnabled(id) ? 'available' : 'unavailable'}`)}</span>
          </div>
        ))}
        <p className="helpText">{app.translator.trans(`${prefix}.admin.audit_help`)}</p>
      </div>
    );
  }
}
