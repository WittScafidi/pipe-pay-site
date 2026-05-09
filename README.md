# pipe-pay-site

Marketing + commerce site for Pipe Pay, live at https://pipepay.app. Self-hosted WordPress + WooCommerce on a Dell OptiPlex behind Cloudflare Tunnel.

## Layout

```
pipepay-child/       Custom GeneratePress child theme - the actual look and feel
mu-plugins/          Must-use plugins (license-resolver endpoint)
CLAUDE.md            Site-ops source of truth (server, nginx, Cloudflare, WC, theme, todos)
homepage-copy.md     Early planning doc (historical reference)
```

## Read CLAUDE.md first

Everything you need to operate the site is in [CLAUDE.md](CLAUDE.md): server credentials path, Cloudflare cache rules (and the rule-order trap), WooCommerce config, theme structure, common ops cheatsheet, and the open to-do list.

## Sync to live

Theme changes are synced via the `tar czf` / `scp` / `tar xzf` cheatsheet command at the bottom of CLAUDE.md. There is no automated deploy - the repo is a developer source-of-truth, not a deploy mechanism.

## Related

- Plugin code: [`wittscafidi/pipe-pay-plugin`](https://github.com/WittScafidi/pipe-pay-plugin) (separate repo)
- Live site: https://pipepay.app
- Old domain (permanent 301): https://pipepay.money

## License

Proprietary. All rights reserved.
