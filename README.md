# Author Moderation

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/huseyinfiliz/author-moderation.svg)](https://packagist.org/packages/huseyinfiliz/author-moderation) [![Total Downloads](https://img.shields.io/packagist/dt/huseyinfiliz/author-moderation.svg)](https://packagist.org/packages/huseyinfiliz/author-moderation)

A [Flarum](https://flarum.org) extension that gives discussion authors administrator-controlled permissions in their own discussions.

Requires Flarum **2.0.0 or later** and PHP **8.3 or later**.

## Features

- Let authors edit posts, hide replies and restore replies in their own discussions.
- Allow authors to rename, hide and restore their own discussions, including after other members have replied.
- Grant each capability independently through Flarum's group permissions.
- Protect selected groups from author moderation. Administrators are exempt automatically.
- Offer an author-only reply mode for journals, progress logs and similar discussions.
- Optionally restrict author permissions using FoF Moderator Warnings points.
- Integrate with Flarum Lock and Audit.

## Installation

```sh
composer require huseyinfiliz/author-moderation:"*"
```

Enable **Author Moderation** in the administration panel.

## Configuration

Use the extension's **Permissions** tab to choose which groups receive each author
capability. Member groups receive no additional permissions automatically. Existing
staff permissions remain unchanged.

In **Moderation**, choose whether authors may restore content hidden by someone
else. By default, they can only restore content they hid themselves. Hiding uses
soft deletion; permanent deletion remains subject to Flarum's existing permissions.
Authors with reply moderation permissions can also view hidden replies in their
own discussions.

### Author-only replies

When enabled on a discussion, only its author and groups with the reply-bypass
permission can reply. This is useful for personal journals and progress logs.
Reading access is unchanged, and other reply restrictions still apply.

Disabling the feature or removing the author's eligibility suspends the restriction
without erasing the saved mode.

### Optional integrations

| Extension | Integration |
| --- | --- |
| [Flarum Lock](https://github.com/flarum/lock) | Allow authors to lock and unlock their own discussions, including locks placed by staff. As in core, users who can manage a lock can reply in the locked discussion. |
| [FoF Moderator Warnings](https://github.com/FriendsOfFlarum/moderator-warnings) | Set an optional active warning-point threshold in **Eligibility**. Additional author permissions are unavailable at or above the threshold. Retracted warnings do not count. |
| [Flarum Audit](https://github.com/flarum/audit) | Record author-only mode changes alongside the existing moderation audit records. |

The warning-point condition defaults to off and is not applied while Moderator
Warnings is disabled or absent.

## Updating

```sh
composer update huseyinfiliz/author-moderation:"*"
php flarum migrate
php flarum cache:clear
```

## Links

- [Packagist](https://packagist.org/packages/huseyinfiliz/author-moderation)
- [GitHub](https://github.com/huseyinfiliz/author-moderation)
- [Original proposal](https://discuss.flarum.org/d/23178-can-authors-automatically-moderate-their-threads)
