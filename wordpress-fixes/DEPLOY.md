# 部署说明：如何把本目录的修复落地到 auigetvapeshop.com

## 重要前提

**当前 Cloud Agent 运行环境本身没有任何访问 auigetvapeshop.com 的凭证**（无 SSH/SFTP、
无 wp-admin 登录信息、无 Cloudflare API Token、无 SiteGround 账号）。本仓库（GoogleWeb）
也不是该网站的部署源码仓库——两者是完全独立的两套系统，我没有办法"直接"把改动推到线上站点。

因此本次交付物是：**一套已经写好、可直接部署的修复代码/脚本 + 详细操作手册**，覆盖了审计
报告里能够安全自动化的部分；剩下必须人工在第三方面板操作的部分，整理成了
[`MANUAL-STEPS.md`](./MANUAL-STEPS.md) 清单。

如果希望我（Cursor Cloud Agent）之后能**直接远程执行**这些脚本、真正把改动落地到线上站点，
请到 **Cursor Dashboard → Cloud Agents → Secrets** 里添加以下任意组合（按你能提供的凭证类型选择）：

| 凭证 | 用途 | 从哪里获取 |
|---|---|---|
| `SSH_HOST` / `SSH_PORT` / `SSH_USER` / `SSH_KEY_PATH` | 通过 SSH/SFTP 上传 mu-plugin、运行 WP-CLI 修复脚本 | SiteGround Site Tools → Devs → SSH Keys Manager |
| `REMOTE_WP_ROOT` | 站点在服务器上的绝对路径 | SiteGround Site Tools → 站点信息里的 Document Root |
| `CF_API_TOKEN` / `CF_ZONE_ID` | 通过 Cloudflare API 开启 Brotli/Auto Minify/静态资源缓存规则 | Cloudflare Dashboard → My Profile → API Tokens（建议新建一个仅限该 Zone、仅 `Zone Settings:Edit` + `Page Rules:Edit` 权限的最小化 Token） |

没有这些凭证之前，下面的部署步骤需要**你自己**（或你的运维/开发人员）手动执行一次，
过程都已经写清楚，复制粘贴即可，不需要额外编码。

---

## 目录结构

```
wordpress-fixes/
├── DEPLOY.md                                本文件
├── MANUAL-STEPS.md                          必须人工在面板操作的清单
├── mu-plugins/
│   └── au-iget-security-hardening.php       安全加固 mu-plugin（对应报告 3.2 节）
├── wp-cli/
│   └── fix-content-typos.php                内容拼写错误批量修复脚本（对应报告 2.2 节）
├── apache/
│   └── htaccess-additions.conf              .htaccess 补充片段（安全头/XML-RPC 拦截 备用方案）
├── cloudflare/
│   └── apply-cloudflare-optimizations.sh    Cloudflare 性能优化自动化脚本（对应报告 1.4 节）
└── deploy/
    └── deploy-via-ssh.sh                    一键通过 SSH 部署 mu-plugin + 内容修复脚本
```

## 方式一：有 SSH/SFTP 访问权限 —— 一键脚本部署（推荐）

```bash
export SSH_HOST="你的主机名或IP"
export SSH_PORT="18765"          # SiteGround 常见端口，以实际为准
export SSH_USER="你的SSH用户名"
export SSH_KEY_PATH="/path/to/your/private_key"
export REMOTE_WP_ROOT="/home/customer/www/auigetvapeshop.com/public_html"

cd wordpress-fixes/deploy
./deploy-via-ssh.sh --run-cli-fix
```

脚本会：
1. 上传 `au-iget-security-hardening.php` 到 `wp-content/mu-plugins/`（上传即生效，无需在
   后台"插件"页面操作）；
2. 上传内容修复脚本到站点根目录下的 `wordpress-fixes/wp-cli/`；
3. 如果远端有 `wp` 命令（WP-CLI），自动跑一次 **dry-run** 扫描并打印结果（不会自动执行修改）。

跑完之后，人工复核 dry-run 输出，确认无误后 SSH 登录执行：

```bash
cd /home/customer/www/auigetvapeshop.com/public_html
wp eval-file wordpress-fixes/wp-cli/fix-content-typos.php --apply
```

## 方式二：没有 SSH，只有 SFTP/File Manager + wp-admin 后台

1. 通过 SiteGround Site Tools 的 **File Manager**（或任意 FTP 客户端），把
   `wordpress-fixes/mu-plugins/au-iget-security-hardening.php` 上传到网站的
   `wp-content/mu-plugins/` 目录（如果该目录不存在，手动新建一个即可）。上传后立即生效，
   刷新前台页面即可用 `curl -I` 验证安全头是否已出现。
2. 内容修复脚本（`fix-content-typos.php`）需要 WP-CLI 环境才能运行。如果 SiteGround 面板里
   有 "WP-CLI Terminal" / "SSH Terminal" 功能（Site Tools → Devs），可以直接在网页终端里
   粘贴运行，不需要本地 SSH 客户端；如果完全没有命令行入口，只能退化为**人工逐个修改**——
   在 wp-admin 后台商品列表页搜索 "lce"，逐条核对并手动修正标题、Rank Math SEO 标题/描述、
   图片 Alt 文本。工作量取决于命中数量，脚本会先扫描出完整清单方便你按清单核对。

## 方式三：有 Cloudflare API Token —— 自动化性能优化

```bash
export CF_API_TOKEN="你的Token"
export CF_ZONE_ID="auigetvapeshop.com 对应的 Zone ID"

cd wordpress-fixes/cloudflare
./apply-cloudflare-optimizations.sh          # 先预览
./apply-cloudflare-optimizations.sh --apply  # 确认无误后执行
```

## 没有任何以上凭证？

那就照着 [`MANUAL-STEPS.md`](./MANUAL-STEPS.md) 和主报告
[`../reports/auigetvapeshop-com-audit-2026-08-02.md`](../reports/auigetvapeshop-com-audit-2026-08-02.md)
里的路线图，由你的运维/开发同学按优先级手动操作即可；所有脚本文件本身也可以直接当作
"标准操作说明书"来读，每一步都有中文注释。

如果之后把上述任意一组凭证添加到 Cursor Dashboard 的 Secrets 里并告知我，
我可以在下一次运行时直接帮你远程执行部署和验证。
