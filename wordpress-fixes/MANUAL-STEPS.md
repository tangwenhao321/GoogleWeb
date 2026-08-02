# 无法脚本化、需要人工在后台/面板操作的事项

以下事项涉及第三方商业面板（SiteGround / Cloudflare Dashboard / Woodmart 主题设置）或业务判断，
没有公开可靠的 API，或风险过高不适合无人值守自动执行，需要人工登录操作。按优先级排列。

## P0 · SiteGround Site Tools — 收窄 Bot 验证码拦截范围（对应审计报告 0.2 节）

**问题**：首页/商品页/分类页会随机（约 20% 概率）被 `sg-captcha` 拦截，返回空白挑战页并附带
`x-robots-tag: noindex`，对 Googlebot 与真实用户都造成误伤。

**操作路径**：
1. 登录 SiteGround Site Tools → 左侧菜单 **Security**。
2. 依次检查以下几个模块，把"挑战/验证码"类规则的生效范围**限制在敏感入口**：
   - **AI Bot Blocking**：如果开启了针对"所有页面"的挑战，改为只作用于 `/wp-login.php`、
     `/wp-admin/*`、`/xmlrpc.php`、`/wp-json/wp/v2/users*`。
   - **Bad Bot Protection / Brute Force Protection**：同上，检查规则匹配路径是否被设置为全站（`/*`）。
3. 如果面板里找不到按路径过滤的选项（不同 SiteGround 套餐界面略有差异），可以：
   - 联系 SiteGround 支持工单，说明"Bot 保护误伤了首页和 Googlebot 抓取，导致间歇性 noindex"，
     请求人工调整规则范围或提高误报阈值；
   - 或者暂时关闭该防护模块，转而依赖 Cloudflare 的 **Bot Fight Mode**（设置为 Managed Challenge
     而非 JS Challenge，且默认只对可疑流量生效，不会覆盖常规页面）。
4. 调整后，用 `curl -A "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)" -I https://auigetvapeshop.com/` 连续测试 10 次，确认不再出现 `sg-captcha: challenge` 与 `x-robots-tag: noindex`。
5. 前往 Google Search Console → 网址检查 → 对首页和几个核心商品页做 "Live Test"，确认返回 200
   且无 noindex 后，点击"请求编入索引"。

## P1 · Cloudflare Dashboard — 图片自动转 WebP/AVIF（对应报告 1.2 节）

**为什么不能脚本化**：Polish 功能需要 **Pro 及以上套餐**才能通过 API/面板启用，且开启后需要观察
一段时间图片质量与命中率，不适合无人值守直接改动。

**操作路径**：
1. Cloudflare Dashboard → 选中 zone → **Speed → Optimization**。
2. 找到 **Polish**，选择 "Lossy"（有损，压缩率更高）或 "Lossless"（无损，更保真），同时开启
   **WebP 转换**。如果套餐里能看到 **Cloudflare Images** 或类似的 AVIF 选项，一并开启。
3. 如果当前套餐是 Free，无法使用 Polish，替代方案是在 WordPress 里安装免费版
   **ShortPixel** 或 **SG Optimizer**（如果主机是 SiteGround，其自带的 SG Optimizer 插件
   本身就有免费的图片优化+WebP/AVIF自动生成功能，登录 wp-admin → Speed 相关菜单开启即可，
   这是本次评估里最推荐的免费路径）。

## P1 · Woodmart 主题设置 — 合并 CSS/JS、按需加载（对应报告 1.3 节）

**为什么不能脚本化**：主题选项存储结构因版本而异，盲猜 `wp_options` 里的 key 直接改数据库
风险很高（可能导致后台白屏或前台样式错乱），必须通过主题自带的设置面板操作，且改完建议
立即人工检查前台渲染是否正常。

**操作路径**：
1. wp-admin → **Woodmart（主题名）→ Theme Settings → Performance**（不同版本菜单路径可能是
   "外观 → Woodmart → 性能" 或独立的 "Woodmart Settings" 顶级菜单）。
2. 找到 **"Combine CSS files"** 与 **"Combine JS files"**（或类似措辞的选项），开启。
3. 找到 **"Lazy load"** 相关选项，确认已针对商品列表图、文章列表图开启；如果主题自带
   "首屏图片不懒加载"或"优先加载首图"选项，一并开启，对应审计报告里 LCP 优先级缺失的问题。
4. 开启后，**务必**在无痕窗口里把首页、`/shop/`、任意一个商品详情页完整走一遍（含加入购物车），
   确认没有样式错位或功能失效，再对外发布。
5. 评估是否真的需要 Slider Revolution 首页轮播（见报告 1.3 节最后一段），如果决定移除，
   直接在 Elementor 编辑器里把对应模块换成静态 Hero 图区块即可，不需要额外代码。

## P2 · SiteGround — PHP 版本 / OPcache / Redis 对象缓存（对应报告 1.5 节）

**操作路径**：
1. Site Tools → **Speed → PHP Manager**，确认 PHP 版本为 **8.2 或 8.3**（低于该版本建议升级，
   升级前先在 Staging 环境测试兼容性，尤其 Slider Revolution/Elementor 等大插件）。
2. Site Tools → **Speed → Caching**，确认 **Dynamic Cache** 已开启，并检查排除规则里包含
   `/cart/`、`/checkout/`、`/my-account/`（避免不同用户看到彼此的购物车/账户状态）。
3. 如果套餐支持（GrowBig 及以上），开启 **Memcached**；WooCommerce 站点强烈建议配合安装
   **Redis Object Cache** 或 **W3 Total Cache/WP Rocket** 的对象缓存对接。

## P0 · 法律合规评估（对应报告 0.1 / 3.1 节）

这一项**没有任何技术脚本可以替代**，必须由人来做决策：
1. 联系熟悉 TGA（Therapeutic Goods Administration）监管框架的澳洲当地法律顾问，
   针对当前"在线零售一次性调味电子烟给消费者"的模式做合规评估。
2. 在拿到法律意见之前，同步推进报告 3.1/3.3 节里的风险分散措施（多域名、多支付通道、
   异地备份），这些属于"抗风险基础设施"，不代表对法律问题的替代方案。

## P2 · 内容与信任信号补充（对应报告 2.4 节）

以下内容需要业务方提供真实信息，无法由代码生成：
- ABN（澳洲商业编号）/ 注册地址 / 客服电话，填入页脚和"关于我们"页面；
- 真实的退换货政策、配送时效说明（如果目前是占位文案，需要业务方核实实际流程后更新）；
- 真实客户评论（如果目前评论较少，考虑通过订单后自动邀评邮件积累，而不是购买虚假评论——
  虚假评论一旦被 Google/消费者举报，对信任度是毁灭性打击）。
