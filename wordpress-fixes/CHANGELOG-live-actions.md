# 线上实际操作记录（2026-08-02）

本文件记录本次使用 wp-admin 账号（`tangwenhao`）直接在 **auigetvapeshop.com 生产环境**执行的操作，
每一项都经过我用 `curl` 从外部独立复核（不只依赖后台界面的"已保存/已生效"提示），如实记录成功与失败项。

> ⚠️ 安全提醒（重复一次）：本次使用的 wp-admin 密码是在聊天里以明文形式提供的，操作全部完成后
> **请尽快去后台修改这个密码**。以后如需授权凭证，建议通过 Cursor Dashboard → Cloud Agents →
> Secrets 添加，不会明文留在对话记录里。

## ✅ 确认生效（已用 curl 外部验证）

| 项目 | 操作位置 | 验证方式 | 结果 |
|---|---|---|---|
| 商品 SEO 标题拼写错误修复（"Blackberry lce" → "Blackberry ice"） | 商品 #10642 → Rank Math Edit Snippet → SEO Title | `curl` 抓取 `<title>` 标签 | ✅ 已生效：`<title>Blackberry ice \| Alibarbar 9000 \| AU IGET Vape Shop</title>` |
| 商品 Meta Description 语法修复 | 同上 → Meta Description | `curl` 抓取 `<meta name="description">` | ✅ 已生效：`"Alibarbar 9000 Blackberry Ice disposable vape for Australia — noticeable cooling on the exhale. Shop authentic flavours at AU IGET Vape Shop."` |
| 禁用 RSS/Atom Feed | Security Optimizer → Site Security → Disable RSS and ATOM Feeds | `curl -I https://auigetvapeshop.com/feed/` | ✅ 已生效：返回 302 跳转到首页（feed 已停用） |
| 删除默认 readme.html | Security Optimizer → Site Security → Delete the Default Readme.html | `curl -I https://auigetvapeshop.com/readme.html` | ✅ 已生效：返回 404 |

## ⚠️ 后台操作后【未能验证生效】，需要进一步排查

| 项目 | 操作位置 | 现象 |
|---|---|---|
| 安全响应头（HSTS / X-Frame-Options / Referrer-Policy / Permissions-Policy） | Code Snippets 插件新建片段 "AU IGET - Security Headers (2026-08)"，已多次确认"已激活/Active"、类型为 PHP、运行范围为 "Run everywhere"，代码内容核对无误，也清过一次 SiteGround 缓存 | `curl -I` 反复检测（含 `x-proxy-cache: MISS`，即确认是源站实时生成的响应，非缓存命中），依然**完全没有**出现这几个头 |
| REST API 用户枚举拦截 (`/wp-json/wp/v2/users`) | 同一个 Code Snippets 片段里包含的 `rest_endpoints` 过滤器 | 该接口依然返回完整用户名/链接等数据，未被拦截 |

**技术分析**：Code Snippets 插件界面显示片段"已激活"，但对应的行为（发送响应头、过滤 REST 接口）在源站新鲜生成的响应里始终没有体现，说明这段代码实际上**没有真正被执行**，而不是被缓存挡住的问题。可能原因（无法在当前权限下进一步确认）：
1. Code Snippets 在此安装环境里有某种"安全模式/Safe Mode"或多站点/多环境配置，导致激活状态与实际执行状态不同步；
2. 存在另一层未知的响应头过滤（比如 SiteGround 反向代理层对非标准头做了白名单过滤，只放行它认识的头），这个只有登录 **SiteGround Site Tools 主机面板**或查看服务器错误日志才能确认；
3. 也不排除 Code Snippets 保存时代码被截断/转义出了问题，虽然目视检查代码内容显示正常。

**建议的下一步（两个方案任选其一）**：
- **方案A（更可靠）**：改用本仓库 `wordpress-fixes/apache/htaccess-additions.conf` 里的服务器层配置，通过 SSH/SFTP 或 File Manager 直接写入 `.htaccess`，绕开 PHP/插件执行层，从根本上避免"插件层面看起来生效但实际没生效"的不确定性。这需要 SSH/SFTP 凭证或 SiteGround Site Tools 的 File Manager 访问权限（这两类凭证我目前都没有）。
- **方案B**：联系 SiteGround 技术支持，请他们从服务器日志层面确认 Code Snippets 里的 PHP 代码是否真的被执行，排查是否有反向代理层过滤了自定义响应头。

## ❌ 无法在 wp-admin 权限范围内完成，需要额外凭证/人工决策

| 项目 | 原因 |
|---|---|
| SiteGround Bot 防护（sg-captcha）范围收窄，避免首页/商品页被随机拦截并打 noindex | 该配置在 **SiteGround Site Tools**（主机控制面板），不在 WordPress wp-admin 里，需要单独的 SiteGround 账号登录 |
| Woodmart 主题 "Combine CSS/JS" | 实测当前安装的 Woodmart 版本 **没有**这个选项（可能是较新版本改为动态按需加载策略），需要改用 SiteGround Speed Optimizer 插件的压缩/合并功能实现，本轮未及展开排查其具体开关状态 |
| ABN / 实体地址 / 电话号码补充 | Contact Us / About Us 页面目前只有邮箱 `xiaofuyuan291@gmail.com`，缺少 ABN、地址、电话；这些是真实业务信息，不能由我代为编造，需要业务方提供真实信息后再填入 |
| 澳洲 TGA 合规业务模式评估 | 需要人工联系当地法律顾问，非技术问题 |
| 全站抽样排查其他商品是否有类似的 SEO Title/Description 拼写或语法问题 | 本轮只完成了 1 个商品的定向修复和小范围抽样，未系统性排查全部商品，建议后续用 `wordpress-fixes/wp-cli/fix-content-typos.php` 通过 SSH/WP-CLI 一次性全站扫描（比逐个后台点击效率高得多） |

## 本轮验证时用到的关键 curl 命令（供复现/后续追踪）

```bash
# 标题/描述验证
curl -s -A "Mozilla/5.0 ..." https://auigetvapeshop.com/product/alibarbar-9000-blackberry-lce/ \
  | grep -oE "<title>[^<]*</title>|<meta name=\"description\"[^>]*>"

# 安全头验证
curl -sI https://auigetvapeshop.com/ | grep -iE "strict-transport|x-frame-options|referrer-policy|permissions-policy"

# REST 用户枚举验证
curl -s https://auigetvapeshop.com/wp-json/wp/v2/users

# feed / readme 验证
curl -sI https://auigetvapeshop.com/feed/
curl -sI https://auigetvapeshop.com/readme.html
```

（注：由于 SiteGround 的 bot 验证码会随机拦截约 20% 的请求，返回 202，验证时建议连续请求几次直到拿到 200 再看内容，这本身也是审计报告 0.2 节问题的实际体现。）
