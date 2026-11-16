/**
 * madong workflow demo · 公共脚本（各栏目页共享，禁止复制粘贴）
 *
 * 提供：API 封装 / message 提示 / 状态徽标与时间格式化 / 用户切换（头像下拉）
 *       栏目页注册与按需加载（fetch /views/{name}.html 注入内容区并执行脚本）
 *       mountTable：layui.table 公共封装（列表页表格 + 内置分页）；mountPager 为旧版分页条（保留兼容）
 */
window.App = (function () {
  'use strict';

  // 状态字典（配色与 jeeflow 徽标体系一致：doing 橙 / done 绿 / reject 红 / info 蓝 / gray 灰）
  const INSTANCE_STATES = {
    0: ['运行中', 'doing'], 1: ['已完成', 'done'], 2: ['已拒绝', 'reject'],
    3: ['已挂起', 'info'], 4: ['已撤回', 'gray'], 5: ['已作废', 'gray'],
  };
  const TASK_STATES = {
    0: ['待办', 'doing'], 1: ['已办结', 'done'], 2: ['已取消', 'gray'],
    3: ['预留', 'gray'], 4: ['已挂起', 'info'],
  };
  const SUBMIT_TYPES = { agree: '同意', reject: '拒绝' };

  const state = {
    operator: localStorage.getItem('wf_demo_operator') || 'user1',
    users: [],
    pages: {},        // name -> { reload }（栏目页注册的钩子）
    loadedPages: {},  // name -> true（HTML 已注入内容区）
    activePage: null,
  };

  // ---------- 基础工具 ----------

  async function api(path, options = {}) {
    const resp = await fetch('/api' + path, {
      headers: { 'Content-Type': 'application/json' },
      ...options,
      body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    });
    const json = await resp.json().catch(() => ({ code: -1, msg: '响应解析失败' }));
    if (json.code !== 0) {
      throw new Error(json.msg || ('HTTP ' + resp.status));
    }
    return json.data;
  }

  /** 顶部居中胶囊 message（jeeflow jf-toast 规格：自动消失、状态色） */
  function message(text, type = 'info') {
    let host = document.getElementById('messageHost');
    if (!host) {
      host = document.createElement('div');
      host.id = 'messageHost';
      document.body.appendChild(host);
    }
    const el = document.createElement('div');
    el.className = 'message ' + type;
    el.textContent = text;
    host.appendChild(el);
    setTimeout(() => {
      el.classList.add('leave');
      setTimeout(() => el.remove(), 220);
    }, 2400);
  }

  function badge(map, v) {
    const item = map[v] || ['未知', 'gray'];
    return '<span class="badge ' + item[1] + '">' + item[0] + '</span>';
  }

  function fmtTime(t) {
    if (!t || Number(t) <= 0) return '-';
    const d = new Date(Number(t) * 1000);
    const p = (n) => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate())
      + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
  }

  function userLabel(name) {
    const u = state.users.find((x) => x.name === name);
    return u ? u.display_name : (name || '-');
  }

  function avatarLetter(displayName) {
    return (displayName || '?').slice(0, 1);
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // ---------- 用户切换（头像下拉，由 layout 调用 initUserSwitch） ----------

  function renderUserChip() {
    const label = userLabel(state.operator);
    const av = document.getElementById('userAvatar');
    const nm = document.getElementById('userDisplayName');
    if (av) av.textContent = avatarLetter(label);
    if (nm) nm.textContent = label;
  }

  function renderUserMenu() {
    const menu = document.getElementById('userMenu');
    if (!menu) return;
    // 图 3 规格：头像 + 姓名（身份）主列 + 右侧账号码，当前账号浅蓝高亮
    menu.innerHTML = state.users.map((u) =>
      '<div class="u-item ' + (u.name === state.operator ? 'active' : '') + '" data-user="' + esc(u.name) + '">'
      + '<span class="avatar">' + esc(avatarLetter(u.display_name)) + '</span>'
      + '<span class="u-name" title="' + esc(u.display_name) + '">' + esc(u.display_name) + '</span>'
      + '<span class="u-code">' + esc(u.name) + '</span>'
      + '</div>'
    ).join('');
  }

  async function loadUsers() {
    state.users = await api('/users');
    renderUserChip();
    renderUserMenu();
  }

  function initUserSwitch() {
    const chip = document.getElementById('userChip');
    const menu = document.getElementById('userMenu');
    if (!chip || !menu) return;

    chip.addEventListener('click', (e) => {
      if (e.target.closest('.u-item')) return;
      menu.classList.toggle('show');
    });

    menu.addEventListener('click', (e) => {
      const item = e.target.closest('.u-item[data-user]');
      if (!item) return;
      menu.classList.remove('show');
      switchUser(item.dataset.user);
    });

    document.addEventListener('click', (e) => {
      if (!chip.contains(e.target)) menu.classList.remove('show');
    });

    loadUsers().catch((err) => message('用户加载失败：' + err.message, 'error'));
  }

  async function switchUser(name) {
    if (name === state.operator) return;
    state.operator = name;
    localStorage.setItem('wf_demo_operator', name);
    renderUserChip();
    renderUserMenu();
    message('已切换用户：' + userLabel(name), 'success');
    // 当前活动栏目立即刷新（其余栏目在下次打开时自然按新 operator 拉数据）
    if (state.activePage) await reloadPage(state.activePage);
  }

  // ---------- 栏目页按需加载（fetch 注入 + 执行 script） ----------

  function registerPage(name, hooks) {
    state.pages[name] = hooks;
  }

  async function reloadPage(name) {
    const page = state.pages[name];
    if (page && typeof page.reload === 'function') {
      try {
        await page.reload();
      } catch (err) {
        message(err.message, 'error');
      }
    }
  }

  async function openPage(name) {
    const container = document.getElementById('pageContainer');
    if (!container) return;
    try {
      if (!state.loadedPages[name]) {
        const res = await fetch('/views/' + name + '.html');
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const html = await res.text();
        const tpl = document.createElement('div');
        tpl.innerHTML = html;
        // innerHTML 注入的 script 不执行，需重建为可执行元素；收集后延到栏目激活再执行——
        // 页面脚本会测量布局（如 layui.table.render 需容器已有宽度，display:none 下渲染会以列宽总和兜底导致表格溢出）
        const pageScripts = [];
        tpl.querySelectorAll('script').forEach((old) => {
          const s = document.createElement('script');
          s.textContent = old.textContent;
          old.replaceWith(s);
          pageScripts.push(s);
        });
        Array.from(tpl.children).forEach((node) => {
          if (node.tagName === 'SCRIPT') return;
          container.appendChild(node);
        });
        state.loadedPages[name] = true;
        // 先激活栏目（容器参与布局），再执行页面脚本
        container.querySelectorAll('section.page').forEach((p) => {
          p.classList.toggle('active', p.dataset.page === name);
        });
        pageScripts.forEach((s) => container.appendChild(s));
      }
      container.querySelectorAll('section.page').forEach((p) => {
        p.classList.toggle('active', p.dataset.page === name);
      });
      document.querySelectorAll('#sideMenu .menu-item').forEach((mi) => {
        mi.classList.toggle('active', mi.dataset.page === name);
      });
      state.activePage = name;
      await reloadPage(name);
    } catch (err) {
      message('页面加载失败：' + err.message, 'error');
    }
  }

  // ---------- 公共分页（列表页共享，前端分页；禁止各页面复制粘贴） ----------

  /**
   * 挂载分页条到 el：opts = { sizes:[10,20,50], onPage(page,size) }
   * 用法：加载/过滤后调用 pager.update(total) 更新总数；渲染时用 pager.page/pager.size 切片。
   */
  function mountPager(el, opts = {}) {
    const sizes = opts.sizes || [10, 20, 50];
    const st = { page: 1, size: sizes[0], total: 0 };

    function draw() {
      const pages = Math.max(1, Math.ceil(st.total / st.size));
      if (st.page > pages) st.page = pages;
      if (st.page < 1) st.page = 1;
      el.innerHTML =
        '<span class="pager-total">共 ' + st.total + ' 条</span>'
        + '<select class="pager-size">' + sizes.map((s) =>
          '<option value="' + s + '"' + (s === st.size ? ' selected' : '') + '>' + s + ' 条/页</option>').join('') + '</select>'
        + '<div class="pager-nav">'
        + '<button class="pager-btn" data-op="prev"' + (st.page <= 1 ? ' disabled' : '') + ' title="上一页">‹</button>'
        + '<span class="pager-cur">' + st.page + ' / ' + pages + '</span>'
        + '<button class="pager-btn" data-op="next"' + (st.page >= pages ? ' disabled' : '') + ' title="下一页">›</button>'
        + '</div>';
    }

    el.classList.add('pager');
    el.addEventListener('change', (e) => {
      if (!e.target.classList.contains('pager-size')) return;
      st.size = Number(e.target.value) || sizes[0];
      st.page = 1;
      draw();
      if (opts.onPage) opts.onPage(st.page, st.size);
    });
    el.addEventListener('click', (e) => {
      const btn = e.target.closest('.pager-btn');
      if (!btn || btn.disabled) return;
      st.page += btn.dataset.op === 'prev' ? -1 : 1;
      draw();
      if (opts.onPage) opts.onPage(st.page, st.size);
    });
    draw();

    return {
      get page() { return st.page; },
      get size() { return st.size; },
      /** 更新总条数并重绘（页码自动收敛） */
      update(total) { st.total = Number(total) || 0; draw(); },
    };
  }

  // ---------- 公共 layui.table 封装（列表页共享；data 模式 + 内置分页 + tool 事件） ----------

  /**
   * 计算表格自适应全屏高度：layui 'full-N' 的 N 是「视口高度要扣除的间隙」，
   * 故 N = 表格顶部偏移（视口坐标）+ 主区底部留白，最终表格高度 = 视口高度 - N。
   * 窗口 resize 时 layui 会自动重算，表格高度固定不随内容撑开。
   * 注意：锚点必须处于可见布局中（页面激活/页签面板展示后）才能得到正确的 rect.top。
   */
  function resolveFullHeight(anchor) {
    const gap = Math.round(anchor.getBoundingClientRect().top) + 28;
    return 'full-' + Math.max(100, gap);
  }

  /**
   * 挂载 layui 表格（data 模式：页面本地过滤后传入数据，layui 负责切片分页渲染）。
   * opts = {
   *   id      表格唯一 id（作为 lay-filter，事件命名空间）
   *   cols    layui 列配置（templet 函数返回单元格 HTML；操作列按钮带 lay-event）
   *   onTool  操作列回调 (obj) => {}（obj.event / obj.data 为整行数据）
   *   page    分页配置覆盖；false 关闭。默认 count/prev/page/next/limits + [10,20,50]
   *   sizes   可选每页条数（默认 [10,20,50]）
   *   empty   空态文案
   *   height  高度模式：默认 'auto'（自适应全屏，锚点位置推算 full-N）；传 false 不限高；数字/'full-N' 原样透传
   * }
   * 返回 { update(data, emptyText) } —— 加载/搜索后调用以重载（保留分页状态）。
   * DOM：layui 把视图插到 elem 之后（elem 仅作锚点），故在挂载元素内部包一层锚点容器，
   * 使 .layui-table-view 落在挂载元素内部（约定选择器 `{挂载元素} .layui-table-main tbody tr`）。
   */
  function mountTable(el, opts = {}) {
    const table = window.layui && window.layui.table;
    if (!table) throw new Error('Layui 未加载');
    const viewId = 'tbl-' + (opts.id || el.id || ('x' + Math.random().toString(36).slice(2, 8)));
    const anchor = document.createElement('div');
    el.innerHTML = '';
    el.appendChild(anchor);
    const height = opts.height === false
      ? undefined
      : (opts.height === undefined || opts.height === 'auto' ? resolveFullHeight(anchor) : opts.height);
    // 全局统一序号列：固定宽度、不排序、按当前页从 1 连续编号（layui type:'numbers'）
    const cols = opts.cols || [[]];
    const seqCol = opts.seq === false ? [] : [{ type: 'numbers', title: '序号', width: 64 }];
    cols[0] = seqCol.concat(cols[0]);
    table.render({
      elem: anchor,
      id: viewId,
      height,
      data: opts.data || [],
      cols,
      page: opts.page === false ? false : Object.assign({
        limits: opts.sizes || [10, 20, 50],
        groups: 3,
        layout: ['count', 'prev', 'page', 'next', 'limits'],
      }, opts.page || {}),
      even: true,
      text: { none: opts.empty || '暂无数据' },
    });
    if (typeof opts.onTool === 'function') {
      table.on('tool(' + viewId + ')', opts.onTool);
    }
    return {
      id: viewId,
      /** 数据/空态更新（reloadData 保留列配置与分页状态） */
      update(data, emptyText) {
        table.reloadData(viewId, { data: data || [], text: { none: emptyText || opts.empty || '暂无数据' } });
      },
    };
  }

  return {
    state, api, message, badge, fmtTime, userLabel, avatarLetter, esc,
    initUserSwitch, switchUser, mountPager, mountTable, resolveFullHeight,
    registerPage, openPage, reloadPage,
    INSTANCE_STATES, TASK_STATES, SUBMIT_TYPES,
  };
})();
