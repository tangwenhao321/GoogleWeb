# AU IGET Vape Shop（auigetvapeshop.com）全站诊断与优化方案

- 诊断对象：https://auigetvapeshop.com/
- 诊断方式：站点抓取（curl / HTTP 头分析）+ 源码解析（HTML/CSS/JS）+ robots.txt / sitemap 分析 + 澳洲 TGA 法规检索
- 技术栈快照：WordPress + WooCommerce 10.9.4 + Woodmart 主题（child theme）8.2.7 + Elementor 4.1.5 + Slider Revolution 6.7.35 + Rank Math SEO + WP Statistics + Contact Form 7 + Variation Swatches for WooCommerce；托管商为 **SiteGround**（识别依据：`sg-captcha`、`/.well-known/sgcaptcha/` 挑战页），前置 **Cloudflare**（橙色云代理）。

> ⚠️ 本报告按你要求的 4 大板块展开，但在此之前必须先说明 **2 个已实测确认、优先级最高的问题**，它们会直接决定后面所有优化投入的 ROI。

---

## 0. 必须优先处理的两个高危项（实测确认）

### 0.1 法律合规风险（最高优先级，超过任何 SEO/性能优化）

自 **2024 年 7 月 1 日**起，《Therapeutic Goods and Other Legislation Amendment (Vaping Reforms) Act 2024》正式生效，澳洲联邦对电子烟的监管发生根本性变化：

- 所有 vape（无论是否含尼古丁、是否为一次性）**只能通过药房渠道供应**；烟草店、vape 专卖店、便利店、**纯电商网站直接对消费者零售**均属违法（非药房零售商禁止销售任何类型的 vape）。
- 2024 年 10 月起，18 岁以上成年人可在**参与计划的药房**免处方购买 ≤20mg/mL 尼古丁浓度产品，前提是药师判断"临床适宜"；更高浓度或未成年人仍需处方。
- **一次性电子烟（disposable vape）无论是否含尼古丁，其国内制造、供应、商业持有均被明确禁止**。
- 2025 年起强化的产品标准（设备、成分、包装、标签）分阶段实施；风味被限制为薄荷/薰衣草/烟草。
- 广告方面：几乎**全面禁止**电子烟广告/推广/赞助，覆盖所有媒体渠道（含社交媒体）。

站点目前的商业模式——在线零售 IGET、Alibarbar、SP2S、SWIX 等**一次性调味电子烟**给澳洲消费者——与上述联邦法规**直接冲突**。这不是"某个页面写错了合规声明"能解决的问题，而是**业务模式层面的法律风险**，可能引发：

- 支付网关（Stripe/PayPal/银行商户账户）风控冻结、扣款、永久拒绝合作；
- 域名注册商/主机商依据 AUP（可接受使用政策）下架站点或域名；
- 广告平台（Google Ads/Meta）100% 无法投放，自然流量也可能因 YMYL（Your Money or Your Life，健康敏感类目）+ 违法销售信号被 Google 人工审核降权甚至从索引移除；
- 监管机构（TGA）投诉举报、罚款、刑事责任风险。

**建议**：在投入任何 SEO/性能资源之前，先由熟悉澳洲 TGA 法规的当地法律顾问评估现有业务模式，考虑以下方向之一：
1. 转型为**信息/评测/导购站 + 联盟营销**（不直接完成在线交易，引导用户到合规渠道），或
2. 严格按 TGA 药房供应链模式重构（几乎不可行于当前独立站形态），或
3. 明确评估继续现状的风险敞口，并同步做好第 3 部分中的"防封抗风险"基础设施（多域名、支付冗余、异地备份等），把停摆损失降到最低。

后文的 SEO/性能/体验建议仍然有效并会照常给出，但请把这条风险放在决策的最前面。

### 0.2 首页/核心页面被自家安全插件间歇性拦截并打上 noindex（技术层高危，可直接导致收录异常）

实测（多次请求，含 `Googlebot/2.1` UA 与普通浏览器 UA）：

```
curl -s -D - -o /dev/null https://auigetvapeshop.com/
HTTP/2 202
server: cloudflare
sg-captcha: challenge
x-robots-tag: noindex
cache-control: no-store,no-cache,max-age=0
```

响应体是一个跳转到 `/.well-known/sgcaptcha/` 的挑战页，页面内容仅 168 字节，**没有任何真实内容**：

```html
<html><head><link rel="icon" href="data:;"><meta http-equiv="refresh" content="0;/.well-known/sgcaptcha/?r=%2F&y=ipr:..."></meta></head></html>
```

连续 5 次请求首页，出现 1 次 202（挑战）+ 4 次 200（正常），比例约 20%；产品页、`wp-login.php`、`readme.html`、`/wp-json/wp/v2/users` 同样会随机命中该挑战。这来自 **SiteGround 主机自带的 SG Security / Bot 保护**（与 Cloudflare 无关，是源站层拦截）。

风险点：
1. `x-robots-tag: noindex` 会被 Googlebot 当作**强制信号**，如果 Googlebot 抓取时恰好命中挑战页（有一定概率，尤其是数据中心 IP 段密集抓取时），会造成首页/产品页在 GSC 中出现"已发现但未编入索引"或"因 noindex 而排除"的间歇性状态，这类"时有时无"的信号对 Google 的信任评分是负面的。
2. 真实用户（尤其是通过广告联盟链接、社媒引流、境外网络出口 IP）也可能被误判为 bot，看到一个空白挑战页而不是首页，直接造成跳出。
3. `wp-login.php` 被挑战本身是**合理**的安全行为，但被套用在首页/商品页/分类页则是"误伤"。

**建议（立即处理）**：
1. 登录 SiteGround Site Tools → Security，找到 **AI Bot Blocking / Bad Bot Protection / Brute-force Protection** 相关设置，将挑战规则的作用范围限制在 `/wp-login.php`、`/wp-admin/`、`/xmlrpc.php`、`/wp-json/wp/v2/users` 等敏感入口，**排除**首页、商品页、分类页、Sitemap。
2. 在 Google Search Console → 设置 → 抓取统计信息，交叉核对是否有大量 5xx/其他状态码的抓取记录，确认问题历史影响范围。
3. 若 SG 安全插件无法精细排除路径，考虑将该防护迁移到 Cloudflare 层（WAF 自定义规则 + Bot Fight Mode 的"验证但不拦截"模式），并对 Googlebot/Bingbot 官方 IP 段或经过反向 DNS 验证的已知搜索引擎爬虫做白名单。
4. 处理完成后，用 GSC "网址检查"工具对首页、`/shop/`、核心商品页做 **Live Test**，确认返回 200 且无 `noindex`，再手动提交重新收录。

---

## 1. 页面加载速度性能优化

### 1.1 现状实测数据

| 项 | 实测结果 |
|---|---|
| `/shop/` 页面 `<link rel="stylesheet">` 数量 | **55 个**（Woodmart 主题把 CSS 拆成大量 "parts" 文件，未合并） |
| `/shop/` 页面 `<script src=...>` 数量 | **53 个** |
| 单张产品图 `alibarbar-9000-blackberry-lce.jpg` | 93.8KB，格式为 **JPEG**（非 WebP/AVIF），`cache-control: max-age=31536000`（缓存策略正确） |
| `loading="lazy"` 出现次数（shop 页） | 仅 10 处，覆盖不全 |
| `fetchpriority="high"`（LCP 图片优先级提示） | **0 处**，未设置 |
| 首页/商品页命中 SG 验证码挑战时的 TTFB 体验 | 直接返回空白重定向页，视觉上等于白屏 |
| HTTP 协议 | HTTP/2（h2），支持 `alt-svc: h3`（QUIC/HTTP3 已宣告但需确认实际启用） |

插件/主题清单（对性能有直接影响的）：
- **Slider Revolution**（revslider）：首页大图轮播，历史上是 WordPress 生态中最常出现严重安全漏洞（多次 CVE，曾被用于批量入侵）且渲染开销较大的插件之一，首屏加载了 `sr7.css`、`tptools.js`、`sr7.js` 等多个脚本。
- **Elementor**：用于文章/落地页构建，每篇内容会额外生成 `post-XX.css`（如 `post-17.css`、`post-232.css`），页面越多产生的孤立 CSS 文件越多。
- **Woodmart 主题**：把基础样式拆成 30+ 个 `css/parts/*.min.css` 独立请求（价格筛选、星级评分、购物车侧栏、搜索弹层等，很多在当前页面根本用不到但仍被加载）。
- **WP Statistics**：前端注入 `tracker.js`，属于自建埋点，比第三方 GA/Meta Pixel 更"安全"（数据不出站），但仍是额外请求。
- **Rank Math**：SEO 插件本身对前端性能影响很小（主要输出 JSON-LD/Sitemap），保留即可。

### 1.2 图片优化

1. **格式**：全站图片仍是 JPEG/PNG，未使用 WebP/AVIF。建议：
   - 开启 Cloudflare 的 **Polish（有损/无损压缩）+ WebP 自动转换**（需 Pro 及以上套餐），或在 SiteGround 后台开启 **SG Optimizer 插件**的"Image Optimization"模块（自动生成 WebP/AVIF + 响应式 `srcset`）。
   - 商品图统一走一次批量重新导出流程：产品主图控制在 1600px 长边以内，详情页场景图/氛围图（`*-scene-1.jpg`、`*-metaphor-1.jpg`）控制在 1200px 以内，压缩质量 75-82。
2. **懒加载补全**：目前只有 10 个元素带 `loading="lazy"`，商品列表页的第 2 屏之后所有图片都应加上；首屏第一张大图（LCP 元素，通常是 Slider Revolution 首图或首个商品卡片图）应显式加 `fetchpriority="high"` 并**禁止**懒加载，这块目前完全空缺，是 LCP 分数的直接扣分项。
3. **响应式 `srcset`**：确认 WooCommerce 的 `add_image_size` 输出了移动端专用尺寸（当前产品页图集包含多张同名重复文件如 `blackberry-lce-scene-1.jpg` 被引用两次，存在冗余，可在 sitemap 生成逻辑/产品图库配置里去重）。

### 1.3 插件精简与合并

1. **CSS/JS 合并与压缩**：Woodmart 主题设置里有"Combine CSS files"/"Combine JS files"选项（在 Theme Settings → Performance），实测**未开启**（55 个独立 CSS 请求即是证据）。建议开启主题自带合并功能，或叠加使用 **WP Rocket**（付费，兼容 Woodmart 官方推荐）或 **Perfmatters + Autoptimize**（免费向组合）做：
   - CSS/JS 合并 + 压缩 + 关键 CSS（Critical CSS）内联；
   - 按需移除未用到的 WooCommerce 购物车片段脚本、Elementor 前端库在非落地页的加载；
   - 对 Slider Revolution，评估是否可以用**静态 Hero 图 + CSS 动画**或更轻量的 Elementor 原生轮播替代，首屏无脚本依赖，直接砍掉 sr7 相关的 2-3 个请求和渲染阻塞脚本。
2. **移除冗余请求**：`Variation Swatches for WooCommerce`、`Contact Form 7`、购物车片段脚本等应设置为**仅在对应页面加载**（用 Asset CleanUp / Perfmatters 的按页面禁用规则），例如联系表单脚本不应该在商品列表页加载。
3. **jQuery Migrate**：检测到仍加载 `jquery-migrate.min.js`（3.4.1），确认主题/插件是否真的依赖旧版 jQuery API，如无必要可移除以减少 1 个请求和执行时间。

### 1.4 缓存策略

1. **静态资源**：图片缓存头已经是 `max-age=31536000`（1 年），配置正确，保留。
2. **页面级缓存**：确认 SiteGround 的 **Dynamic Cache（NGINX Direct Delivery）** 已开启，且**购物车/结账/我的账户**等动态页面被正确排除全页缓存（避免用户看到别人的购物车状态）；商品/分类/文章页应命中全页缓存。
3. **对象缓存**：建议在 SiteGround 面板开启 **Memcached/Redis Object Cache**（GrowBig 及以上套餐支持），减少 WooCommerce 频繁的数据库查询（商品库存、购物车 session）。
4. **浏览器缓存 + Cloudflare 缓存规则**：在 Cloudflare 面板为 `/wp-content/uploads/*`、`/wp-content/themes/*`、`/wp-content/plugins/*` 设置 Cache Rules，Edge TTL 拉长到 1 个月以上，并开启 **Auto Minify**（HTML/CSS/JS）与 **Brotli** 压缩（目前响应头未见 `content-encoding: br`，建议确认已启用）。

### 1.5 主机层面

1. 当前使用 SiteGround，其 Nginx + Cloudflare 组合本身适合中小型 WooCommerce 站；核心瓶颈不在主机带宽，而在**未合并的前端资源**和**间歇性验证码拦截**（见 0.2）。
2. 建议确认 PHP 版本为 **8.2+**（WooCommerce 10.x 官方要求 PHP 7.4+，8.2/8.3 性能提升明显），并确保 **OPcache** 已开启。
3. 若未来订单量/并发上升，SiteGround 的共享资源模型会成为瓶颈，可评估迁移到专门优化 WooCommerce 的托管（Cloudways + DigitalOcean/Vultr、Kinsta、WP Engine 等），但目前阶段**不是优先级最高的投入**，先把免费/低成本的合并缓存类优化做完。

---

## 2. 澳洲本地 SEO 优化

### 2.1 收录健康度（先修复技术阻塞项）

- **Sitemap 结构正常**：`sitemap_index.xml` → `post-sitemap.xml` / `page-sitemap.xml` / `product-sitemap.xml` / `category-sitemap.xml`，由 Rank Math 生成，格式规范，产品页附带图片子标签，属于良好实践。
- **`robots.txt` 分析**：

```
User-agent: *
Content-Signal: search=yes,ai-train=no,use=reference
Allow: /

User-agent: GPTBot / ClaudeBot / CCBot / Bytespider / Amazonbot / Google-Extended / meta-externalagent
Disallow: /
```

这是 Cloudflare 自动托管的"AI 爬虫拦截"规则，**不影响**常规 Googlebot/Bingbot 的收录（`Google-Extended` 只控制 Gemini 等 AI 特性训练数据抓取，与搜索收录无关），配置合理，无需改动。真正影响收录的是 **0.2 节所述的间歇性验证码拦截**，这是当前最高优先级的 SEO 阻塞项，必须先修。

### 2.2 内容质量问题（实测发现，会被 Google "规模化内容滥用"政策关注）

- **产品标题/描述存在明显拼写错误**：实测商品页 `alibarbar-9000-blackberry-lce` 的 `<title>`、Meta Description、OG 标签、图片 alt 全部写成 **"Blackberry lce"**（应为 "Blackberry Ice"），且这个错误连 URL slug 里都带着，说明是**批量/模板化生成内容**时的通用性错误，很可能不止这一个商品受影响，建议**全站批量检查所有商品/分类的标题与 slug**，逐一修正 "ice" 相关拼写。
- **Meta Description 语法破碎**：实测同一商品的描述为：

  > "Blackberry lce single-taste Alibarbar 9000 disposable for Australia. noticeable cooling on the exhale Shop at AU IGET Vape Shop."

  缺少标点、大小写混乱，明显是未经人工校对的批量/AI 生成文案。这类内容在 Google 2024 年更新的"规模化内容滥用（Scaled Content Abuse）"垂类反滥用政策下有被判定为低质、进而站点级降权的风险，尤其叠加博客同一天连发多篇（首页可见 "02 Aug" 当天发布 3 篇、"01 Aug" 发布 2 篇的节奏），信号叠加后风险更高。
- **建议**：
  1. 建立内容发布前的人工校对流程（至少标题、Meta、H1 三处强制检查），不允许未经校对的模板输出直接上线；
  2. 把博客发布节奏降到可持续、有真实增量信息的水平（例如每周 2-3 篇有实际测评/对比数据的深度内容），而不是同一天批量铺量；
  3. 商品描述改为"模板框架 + 人工补充差异化细节"（口味特征、烟雾量、真实使用场景），避免所有商品描述结构完全一致导致的模板化重复内容问题。

### 2.3 关键词与信息架构

- 品类结构清晰（LTD 9000 / SWIX 9000 / TEQ 12000 / SP2S 12000 / Alibarbar 系列 / IGET BAR PRO / IGET ONE），分类页命中良好，符合"品牌词+型号词"搜索习惯。
- 建议关键词矩阵按**搜索意图分层**：
  - 交易型（高转化）：`buy [brand] [model] australia`、`[flavour] disposable vape australia`、`iget bar pro flavours au`
  - 对比型（博客承接）：`[品牌A] vs [品牌B] disposable vape`、`9000 puffs vs 12000 puffs vape`
  - 信息型（培育权威度）：`how long does a disposable vape last`、`vape puff count explained`
- 但请注意：**在当前 TGA 监管环境下，"buy disposable vapes australia"这类强交易词本身就是高监管敏感词**，即使技术 SEO 做得再好，也可能因为业务合规问题被平台人工审核标记（参见第 0.1 节），关键词策略的上限被法律环境锁死，这是必须提前告知的现实约束。

### 2.4 本地化与信任信号（NAP / E-E-A-T）

实测在商品页、Shop 页全文搜索，**未发现**：
- ABN（澳洲商业编号）/ ACN；
- 实体营业地址；
- 客服电话号码（`+61` 开头）。

对于健康敏感类目（YMYL），Google 的质量评估非常依赖**可验证的运营者身份信息**。建议：
1. 在页脚和"关于我们/联系我们"页面显示 **ABN**、注册地址、客服邮箱与电话（如果合法合规状态允许继续运营）；
2. `Organization` 结构化数据（目前已有 `@type: Organization`）补全 `address`、`telephone`、`sameAs`（社交主页）字段；
3. 增加真实的退换货政策页、配送时效页、隐私政策/Cookie 政策页（并与页面上已出现的 Cookie 提示条联动），这些是本地 SEO 与信任评分的基础项，目前从抓取结果看信息架构里可能缺失或链接层级过深。

### 2.5 结构化数据

- 已实现 `Organization`、`WebSite`、`Product`、`Offer`、`ItemPage`、`ImageObject`、`ListItem`（Breadcrumb），`priceCurrency` 正确标注为 `AUD`，这部分做得不错，建议继续补充 `AggregateRating`/`Review`（若有真实评论）、`FAQPage`（结合博客内容做 FAQ 富媒体摘要）。

---

## 3. WordPress 建站安全合规（Vape 品类特殊风控/防封）

### 3.1 业务模式合规（详见第 0.1 节，此处补充落地动作）

1. 尽快获取澳洲当地（建议同时咨询药事法/广告法背景的律师）关于当前"一次性调味电子烟在线直销"模式的合规意见书，这是所有后续投入的前提。
2. 在等待法律意见期间，**降低单点故障风险**：
   - 域名：使用支持隐私保护（WHOIS Privacy）的注册商，且在**至少 2 个不同注册商**分别注册品牌相关域名（主域名 + 1-2 个备用跳转域名），一旦主域名被举报冻结，可快速切换。
   - 支付：不要仅依赖 Stripe/PayPal（这两家对烟草/尼古丁类目风控极严，历史上大量同类站点被直接永久冻结资金），评估**支持高风险/受限类目**的支付服务商（如部分本地银行商户账户、专门服务受限行业的支付网关），并保留至少 1 个备用支付渠道。
   - 主机与备份：**异地、异主机商**的每日自动备份（数据库 + `wp-content/uploads`），不要只依赖 SiteGround 自带的备份（一旦账号被主机商因合规审查冻结，自带备份也会一起不可访问）。建议用 UpdraftPlus/BackWPup 把备份推送到独立的 S3/Backblaze B2 等第三方存储。
3. 广告与联盟渠道：不要在 Google Ads / Meta Ads 投放（100% 违规会被封户，且波及同主体下的其他广告账户），自然流量 + 邮件列表（站内已有 Newsletter 表单）+ 私域（如 SMS/Telegram）是当前阶段相对安全的获客渠道。

### 3.2 WordPress 技术安全加固（实测发现的具体缺口）

1. **安全响应头缺失**：实测仅有 `x-content-type-options: nosniff`，**缺失** `Strict-Transport-Security`（HSTS）、`Content-Security-Policy`、`X-Frame-Options`、`Referrer-Policy`、`Permissions-Policy`。建议通过 Cloudflare 的 **Transform Rules** 或安全插件（如 iThemes Security / Wordfence）统一加上：

   ```
   Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
   X-Frame-Options: SAMEORIGIN
   Referrer-Policy: strict-origin-when-cross-origin
   Permissions-Policy: geolocation=(), camera=(), microphone=()
   ```

2. **`xmlrpc.php` 返回 520（源站错误）**：实测 `curl -I https://auigetvapeshop.com/xmlrpc.php` 返回 Cloudflare `520`，说明源站在处理该请求时出现异常（可能是规则冲突导致的畸形响应，而非真正禁用）。建议明确地**完全禁用 XML-RPC**（而不是让它以错误状态挂着），可在 `.htaccess` 或安全插件里直接拦截该路径，防止其被用于 XML-RPC pingback 放大攻击或暴力破解穿透。
3. **REST API 用户枚举**：`/wp-json/wp/v2/users` 目前时而被验证码拦截、时而可能放行，建议用安全插件明确**永久禁止匿名访问该端点**，避免用户名被枚举后配合弱密码进行撞库。
4. **`wp-login.php` 防护**：建议启用登录失败限流（Limit Login Attempts）+ 强制双因素认证（尤其管理员/店长账号）+ 修改默认登录路径（结合安全插件做隐藏登录页），当前虽然被 SG 验证码"顺带"保护，但这不是专门设计的登录防护，稳定性存疑（见 0.2）。
5. **插件更新与漏洞面**：**Slider Revolution** 是 WordPress 生态历史上被利用次数最多的商业插件之一（多次高危 CVE，曾被用于批量挂马），当前版本 6.7.35，务必确认已开启自动更新且是从官方渠道获取授权更新（而非破解版，破解版插件是入侵后门的最常见来源之一）。同理检查 Woodmart 主题、Elementor、WooCommerce 是否都在自动更新范围内。
6. **文件编辑与上传限制**：建议在 `wp-config.php` 加入 `define('DISALLOW_FILE_EDIT', true);` 禁用后台主题/插件在线编辑器，减少一旦后台被入侵后的横向破坏面。
7. **数据库前缀与账号最小权限**：确认数据库表前缀非默认 `wp_`，后台账号遵循最小权限原则（客服/运营账号不给管理员权限）。
8. **年龄验证的合规边界**：实测页面存在自定义的 `au-age-card` 弹窗（"18+ 请确认年龄"）与 `au-nicotine-bar`（"含尼古丁警示条"），这是良好的前端 UX 合规展示，但请注意：
   - Woodmart 主题自带的原生年龄验证功能实测处于**关闭状态**（`age_verify: "no"`），当前用的是站点自定义弹窗，需要自行确认其**逻辑健壮性**（是否有 Esc 键/点击遮罩关闭绕过、是否对未成年选择"No"后正确拦截后续浏览/下单）；
   - 纯前端 Cookie 弹窗**不构成法律意义上的实名年龄核实**，如果法律意见认为业务可以在调整后继续运营，需要评估是否需要引入更强的核实机制（如结合支付环节的身份/信用卡验证）。

### 3.3 防封抗风险的整体架构建议

```
用户 → Cloudflare（WAF + Bot Fight Mode + 隐藏源站 IP）
        → SiteGround 源站（收紧 Bot 挑战规则范围，仅保护敏感入口）
           → WordPress（禁用 XML-RPC/用户枚举、强制 2FA、DISALLOW_FILE_EDIT）
              → 数据库/媒体文件 每日异地备份（独立于主机商账号）
支付：主 + 备双通道，避免单一网关冻结导致业务完全停摆
域名：多注册商分散注册 + WHOIS 隐私保护
```

---

## 4. 转化率与用户体验优化

### 4.1 首屏与信任建立

1. 首页已有清晰的品类导航（"Shop by series"：Alibarbar / IGET / SP2S / SWIX）与"Why shop with us"信任模块（Fast AU Shipping / Authentic Brands / Easy Support），结构合理，但**缺少可验证的信任凭证**（ABN、支付方式图标、真实评论数/星级、配送时效具体天数），建议补齐（与第 2.4 节联动）。
2. 首屏 Slider Revolution 大图轮播建议评估是否真的提升转化——大型轮播图在电商站的转化效果长期存在争议（用户很少等待轮播切换），且它是当前性能报告里最重的组件之一（见第 1 节），可以做 A/B 测试：用**静态 Hero + 主推爆款直接可点击**替代轮播，同时收获性能与转化两方面收益。

### 4.2 商品与结账体验

1. 已确认开启 **AJAX 加入购物车**（`woocommerce_ajax_add_to_cart: yes`）与**图片缩放查看**（`zoom_enable: yes`），这两项对移动端购物体验很关键，保留。
2. 建议检查结账流程是否为**单页结账**（One Page Checkout），并确认支持澎湃地区常用支付方式（BPAY、Apple Pay/Google Pay、Afterpay 等分期支付，如合规状态允许）——vape 类目由于主流支付渠道受限，明确展示"我们支持哪些支付方式"本身也是转化率的关键信任因素。
3. 页面中检测到 WooCommerce 默认的 **"Coming Soon" 区块标记**（`data-block-name="woocommerce/coming-soon"`，正常情况下靠 CSS 隐藏，仅在店铺被设为"即将上线"模式时显示）。建议定期确认后台 WooCommerce → 设置 → 一般 里的"Coming soon"模式确实处于关闭状态，避免因主题/插件更新引发的样式冲突导致该区块意外显示，造成"店铺关闭"的错觉。
4. 商品详情页描述文案质量需要与第 2.2 节的内容质量修复同步进行——不只是 SEO 问题，语法破碎的描述本身也会直接降低购买信任感。

### 4.3 移动端与性能对转化的联动影响

1. Vape 品类的移动端下单占比通常很高（社媒引流为主），而第 1 节列出的 55+53 个前端资源请求、频繁出现的验证码空白页（第 0.2 节），会直接体现为**移动端跳出率异常升高**，这是"性能优化"与"转化优化"这两个板块里投入回报最直接挂钩的部分——建议把 0.2 与 1.3 的修复作为提升转化率的第一步，而不是等性能优化"做完"才开始看转化数据。
2. 邮件列表捕获（"Stay in the Loop" Newsletter 表单）已存在，建议补充：
   - 首次访问的**折扣券弹窗**（结合 Exit-Intent，仅在非首页/非结账页触发，避免和年龄验证弹窗叠加造成用户体验混乱）；
   - 弹窗触发逻辑与 `au-age-card` 年龄验证弹窗的优先级排序（避免同时弹出多个模态框）。
3. 购物车/心愿单/对比（Wishlist/Compare，Woodmart 自带功能已在配置中开启）建议在移动端确认图标可见性与点击热区大小，这类插件功能默认样式在小屏上容易出现点击目标过小的问题。

---

## 附：优先级路线图建议

| 优先级 | 事项 | 对应章节 |
|---|---|---|
| P0（立即） | 法律合规评估 | 0.1 / 3.1 |
| P0（立即） | 修复 SG 验证码误伤首页/商品页 + noindex 问题 | 0.2 |
| P1 | 修正商品标题/描述中的拼写与语法错误（"lce"→"Ice"等），全站批量核查 | 2.2 |
| P1 | 补充 ABN/联系方式等信任信号 + 完善结构化数据 | 2.4 / 2.5 |
| P1 | 开启 CSS/JS 合并、图片转 WebP/AVIF、补全懒加载与 LCP 优先级 | 1.2 / 1.3 |
| P2 | 加固安全响应头、禁用 XML-RPC、限制 REST 用户枚举、插件更新审计 | 3.2 |
| P2 | 建立多域名/多支付/异地备份的抗风险架构 | 3.1 / 3.3 |
| P3 | 首屏轮播 A/B 测试、结账支付方式扩展、弹窗策略优化 | 4.1 / 4.2 |
| 持续 | 内容发布节奏与质量管控，避免规模化内容滥用信号 | 2.2 |

---

## 附：可直接落地部署的修复代码与脚本

上表中标注为可自动化的技术项（安全加固、内容拼写批量修复、Cloudflare 性能配置等）已经
写成可直接部署的代码/脚本，见 [`../wordpress-fixes/`](../wordpress-fixes/DEPLOY.md) 目录：

- `wordpress-fixes/mu-plugins/au-iget-security-hardening.php` — 对应第 3.2 节的安全加固（安全响应头、禁用 XML-RPC、阻止用户枚举、禁用文件编辑、隐藏 WP 版本号）。
- `wordpress-fixes/wp-cli/fix-content-typos.php` — 对应第 2.2 节的内容拼写批量核查修复（默认 dry-run，人工复核后再执行）。
- `wordpress-fixes/apache/htaccess-additions.conf` — 服务器层的安全头/XML-RPC 拦截备用方案。
- `wordpress-fixes/cloudflare/apply-cloudflare-optimizations.sh` — 对应第 1.4 节的 Brotli/Auto Minify/静态资源缓存自动化配置（需要 Cloudflare API Token）。
- `wordpress-fixes/MANUAL-STEPS.md` — 无法脚本化、必须人工在 SiteGround/Cloudflare/Woodmart 面板操作的事项清单，按优先级排序。
- `wordpress-fixes/DEPLOY.md` — 完整部署说明，包含需要哪些访问凭证（SSH/Cloudflare API Token）才能把改动真正落地到线上站点。
