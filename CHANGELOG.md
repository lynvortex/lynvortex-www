# 更新日志

本项目遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [1.0.0] — 2026-10-02

首个开源版本。

### 站点结构

- **统一前端样式**：原先手写 CSS 与 Tailwind CDN 两套体系合并为单一
  `assets/css/site.css`，移除 Tailwind CDN 与废弃的 `style.css`。
- **第三方库本地化**：highlight.js / marked / DOMPurify 全部放进
  `assets/vendor/`，前台不再依赖外部 CDN（仅 Google Fonts 例外）。
- **URL 重构**：`/articles/`、`/about/`、`/post/<slug>/` 都改为
  「目录 + index.html」形式，不依赖服务器重写规则，任何静态服务器都能跑。
- **SEO**：每页都有 canonical、Open Graph、Twitter Card 与 JSON-LD；
  新增 `robots.txt`、`404.html`，修正 `sitemap.xml`。
- **旧链接 301**：`*.html`、`post.html?id=<旧ID>` 全部服务端 301 到新地址。

### 写作后台

- 新增密码登录的在线写作后台（`admin/`）：Markdown 编辑、实时预览、
  图片上传、文章增删改。
- 保存时同时更新 `posts.json` 并生成每篇文章的静态页，兼顾易用与 SEO。
- 中文标题自动转拼音 slug（如「用户指南」→ `yong-hu-zhi-nan`）。
- 正文经白名单净化，剔除脚本、事件属性与 `javascript:` 协议。
- CSRF 校验、登录限流（同 IP 15 分钟 8 次）、会话 30 分钟超时。

### 开源改造

- 新增 `site.config.php`：站名、域名、联系方式、友链、备案号集中一处，
  改完运行 `php build.php` 即可换成自己的站点。
- 新增 `build.php`：从配置生成首页、文章列表、关于、404、旧地址兼容页、
  `sitemap.xml`、`robots.txt` 与全部文章页。
- 抽出 `admin/lib/layout.php` 与 `admin/lib/admin_ui.php`，消除导航与页脚的重复。
- 新增「修改密码」页面；使用默认密码时后台顶部持续显示安全告警。
- 补充 `LICENSE`、`.gitignore`、`.gitattributes`。

### 安全提示

- `admin/config.php` 存密码哈希，已加入 `.gitignore`，请勿提交。
- 首次安装预填的默认密码是公开的，上线前必须修改。
