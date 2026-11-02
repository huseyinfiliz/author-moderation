import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import type Mithril from 'mithril';
type Tab = 'moderation' | 'author_only' | 'eligibility' | 'permissions' | 'integrations';
export default class AuthorModerationPage extends ExtensionPage {
    activeTab: Tab;
    sections(vnode: Mithril.VnodeDOM): import("flarum/common/utils/ItemList").default<unknown>;
    content(): JSX.Element;
    moderationSettings(): any[];
    authorOnlySettings(): any[];
    eligibilitySettings(): any[];
    integrations(): JSX.Element;
}
export {};
