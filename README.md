# 绘萤者 Lynvortex

一个 **静态优先** 的个人博客 / 内容站模板：前台是纯静态 HTML，后台是单文件目录的
PHP 写作界面。保存文章时同时更新 `posts.json` 并生成每篇文章的静态页，兼顾
编辑体验与 SEO。

![首页](images/bg.webp)

---

## 特性

**前台**

- 纯静态 HTML，无框架、无构建步骤、无外部 CDN（仅 Google Fonts）
- 全站单一 `assets/css/site.css`，CSS 变量驱动的设计令牌
- 响应式布局、移动端菜单、站内搜索、回到顶部
- 每页都有 canonical / Open Graph / Twitter Card / JSON-LD 结构化数据
- 文章页支持代码高亮、一键复制、阅读进度、分享
- 目录式 URL（`/articles/`、`/post/<slug>/`），**不依赖服务器重写规则**，
  Apache / Nginx / `php -S` / `python -m http.server` 都能直接跑

**后台**

- 密码登录（bcrypt 哈希），CSRF 校验，登录限流，会话超时
- Markdown 编辑器：实时预览、工具栏、快捷键、图片上传
- 中文标题自动转拼音 slug（「用户指南」→ `yong-hu-zhi-nan`）
- 正文白名单净化，剔除脚本与危险属性
- 保存即生成静态页 + 更新 sitemap

**开源友好**

- 站点名称、域名、联系方式、友链、备案号全部集中在 `site.config.php`
- `php build.php` 一键重新生成所有页面

---

## 环境要求

- PHP 7.4 或更高（推荐 8.x）
- 需要 `json`、`mbstring` 扩展（生成拼音 slug 依赖 mbstring）
- Web 服务器：Apache（含 `mod_rewrite`）、Nginx，或 PHP 内置服务器
- 无需数据库

---

## 快速开始

```bash
git clone https://github.com/lynvortex/lynvortex.git my-site
cd my-site
```

**本地预览**

```bash
php -S 127.0.0.1:8000
```

打开 <http://127.0.0.1:8000/>。后台在 <http://127.0.0.1:8000/admin/>。

> ⚠️ 不建议用 `python -m http.server` 预览。Windows 注册表把 `.svg` 登记为
> `image/svg`，浏览器只认 `image/svg+xml`，会导致**全站图标不显示**。
> 这是 Python 开发服务器的 MIME 问题，不是站点缺陷；`php -S` 与 Apache 都正常。

**访问后台**

1. 打开 `/admin/setup.php`
2. 密码框已预填默认密码 **`abc123`**，站点地址取自 `site.config.php`
3. 点「完成安装」→ 用该密码登录

> 🔴 **默认密码 `abc123` 是公开的**（随仓库分发），任何用默认密码上线的后台
> 等于没有密码。登录后请立刻到「修改密码」页面改掉——在你改掉之前，
> 后台顶部会一直显示红色告警。

---

## 换成你自己的站点

编辑根目录的 `site.config.php`：

```php
return [
    'name'        => '你的站名',
    'url'         => 'https://example.com',
    'description' => '站点描述',
    'email'       => 'you@example.com',
    'github'      => 'https://github.com/you/you',
    'friend_links' => [
        ['name' => '友链名称', 'url' => 'https://friend.example.com'],
    ],
    'icp'         => '',   // 不需要备案就留空
    'police'      => '',
    'admin' => [
        'default_password' => 'abc123',
    ],
];
```

然后重新生成所有页面：

```bash
php build.php
```

导航、页脚、meta 标签、结构化数据都会跟着更新。

---

## 部署到虚拟主机

1. 把整个目录（**含 `.htaccess`**）上传到网站根目录。
   FTP 客户端记得开启「显示隐藏文件」。
2. 设置目录可写：

   | 路径 | 用途 |
   |---|---|
   | `admin/` | 生成 `config.php` |
   | `admin/data/` | 登录限流记录 |
   | `posts.json` | 文章数据 |
   | `post/` | 生成文章静态页 |
   | `images/uploads/` | 上传图片 |

   通常 `755`，若主机以 `www-data` 运行 PHP 可能需要 `775`。

3. 浏览器打开 `https://你的域名/admin/setup.php` 完成安装。
4. **删掉 `admin/setup.php`**（安装完成后不再需要）。
5. 确认 `admin/config.php` 无法通过浏览器访问（应返回 404）。

---

## 目录结构

```
.
├── site.config.php          ★ 站点配置（改这里换站）
├── build.php                ★ 静态页生成器
├── index.html               首页（生成）
├── 404.html                 错误页（生成）
├── post.html                旧地址兼容页（生成）
├── post.php                 /post/<slug>/ 的兜底渲染
├── posts.json               文章数据
├── sitemap.xml / robots.txt 生成
├── .htaccess                URL 重写 / 安全头 / 缓存
├── LICENSE / CHANGELOG.md
│
├── articles/index.html      文章列表（生成）
├── about/index.html         关于我们（生成）
├── post/<slug>/index.html   文章页（生成）
│
├── assets/
│   ├── css/site.css         全站样式
│   ├── js/site.js           导航 / 搜索 / 列表渲染
│   ├── js/post.js           文章页增强
│   └── vendor/              highlight / marked / DOMPurify
│
├── images/                  图片资源
│   └── uploads/             上传目录
│
└── admin/                   写作后台
    ├── setup.php            首次安装
    ├── login.php  logout.php
    ├── index.php            编辑器
    ├── password.php         修改密码
    ├── api.php              JSON 接口
    ├── config.php           ★ 密码哈希，勿提交
    ├── data/                运行数据，勿提交
    ├── lib/
    │   ├── bootstrap.php    核心库
    │   ├── layout.php       前台共享布局
    │   ├── admin_ui.php     后台界面片段
    │   ├── render_post.php  文章页渲染
    │   ├── Parsedown.php    Markdown 解析（vendored）
    │   └── pinyin*.php      拼音数据
    └── assets/              后台样式与脚本
```

---

## 写文章

1. 登录 `/admin/`，点「新建文章」
2. 填标题与正文（Markdown），可选 slug、标签、摘要、封面
3. `Ctrl+S` 保存 → `posts.json` 更新、静态页生成、sitemap 更新

**slug** 留空时会从标题自动生成：英文转小写连字符，中文转拼音。

**支持的 Markdown**：标题、粗体、斜体、链接、代码块（带高亮）、引用、列表、
表格、分隔线，以及提示框：

```markdown
> [!NOTE]
> 提示内容

> [!TIP]     绿色
> [!WARNING] 黄色
> [!DANGER]  红色
```

**图集**：

```html
<div class="img-grid">
  <div class="img-item">
    <img src="./images/a.webp" alt="说明">
    <div class="caption">图注</div>
  </div>
</div>
```

> 正文里的图片路径写成 `./images/xxx.webp` 或 `/images/xxx.webp` 都行，
> 生成时会自动规范化为根路径。

---

## URL 规则

| 地址 | 说明 |
|---|---|
| `/` | 首页 |
| `/articles/` | 文章列表（`/articles` 会 301 到带斜杠） |
| `/about/` | 关于我们 |
| `/post/<slug>/` | 文章详情 |
| `/post.html?id=<旧ID>` | 301 到对应文章 |
| `/lynvortex` | StatiCrypt 加密页 |

---

## 安全

- 密码以 `password_hash()`（bcrypt）存储，不存明文
- 写操作校验 CSRF token
- 登录失败限流：同 IP 15 分钟内最多 8 次
- 会话 30 分钟无操作自动过期
- 正文白名单净化
- `.htaccess` 拒绝访问 `admin/config.php`、`admin/data/`、`admin/lib/`
- 前台 CSP 限制为 `img-src 'self' data:`，无外部 CDN 依赖

**上线前检查**

- [ ] 已修改默认密码 `abc123`
- [ ] 已删除 `admin/setup.php`
- [ ] `admin/config.php` 无法通过浏览器读取
- [ ] 已启用 HTTPS
- [ ] `posts.json` 可读，`admin/data/` 不可读

---

## 常见问题

**保存文章失败**
目录权限问题。检查 `posts.json`、`post/`、`admin/data/` 是否可写。

**图标全不显示**
用了 `python -m http.server`。改用 `php -S`，或修正服务器对 `.svg` 的 MIME 映射。

**忘记密码**
删除 `admin/config.php`，重新访问 `admin/setup.php`。文章数据不受影响。

**想换域名**
改 `site.config.php` 的 `url`，改 `.htaccess` 里的 HTTPS 跳转域名，
然后运行 `php build.php` 并点后台「重建静态页」。

**改了配置但页面没变**
配置只影响生成结果，需要重跑 `php build.php`。

---

## 贡献

欢迎提 Issue 与 PR。

- PHP 代码遵循 PSR-12 风格，4 空格缩进
- 提交前请跑一遍 `php -l` 检查语法
- 请勿提交 `admin/config.php` 或 `admin/data/` 下的任何内容

## 许可

[MIT](LICENSE)

## 致谢

- [Parsedown](https://github.com/erusev/parsedown) — Markdown 解析
- [highlight.js](https://highlightjs.org/) — 代码高亮
- [marked](https://marked.js.org/) — 后台实时预览
- [DOMPurify](https://github.com/cure53/DOMPurify) — 预览净化
- [pinyin-data](https://github.com/mozillazg/pinyin-data) — 拼音数据
