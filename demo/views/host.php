<?php
/**
 * workflow demo · 宿主页（原生 JS + iframe 嵌 visual-flow 设计器）
 *
 * 页签：流程设计 / 发起流程 / 待办事项 / 已办事项 / 我的申请 / 流程监控
 * 用户切换：演示用户（张三/李四/王五/赵六/钱七），operator 即身份
 *
 * 设计器协议见 ui/index.html 头部注释：flow.ready / flow.load / flow.save
 */
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>madong/workflow 工作流引擎演示</title>
<style>
  :root {
    --primary: #3068ec;
    --primary-bg: #e8f0fe;
    --ok: #52c41a;
    --warn: #faad14;
    --danger: #f5222d;
    --text: #303133;
    --text-2: #606266;
    --text-3: #909399;
    --border: #e4e7ed;
    --bg: #f5f7fa;
  }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; height: 100%; font-family: "Microsoft YaHei", "PingFang SC", sans-serif; color: var(--text); background: var(--bg); }
  body { display: flex; flex-direction: column; }

  /* 顶栏 */
  .topbar { display: flex; align-items: center; justify-content: space-between; height: 50px; padding: 0 20px; background: #fff; border-bottom: 1px solid var(--border); flex: 0 0 auto; }
  .brand { font-size: 16px; font-weight: 600; }
  .brand small { color: var(--text-3); font-weight: normal; margin-left: 8px; }
  .user-switch { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-2); }
  .user-switch select { padding: 4px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: 13px; }

  /* 页签 */
  .tabs { display: flex; gap: 4px; padding: 8px 20px 0; background: #fff; border-bottom: 1px solid var(--border); flex: 0 0 auto; }
  .tabs button { padding: 8px 18px; border: 1px solid transparent; border-bottom: none; border-radius: 6px 6px 0 0; background: transparent; font-size: 14px; color: var(--text-2); cursor: pointer; }
  .tabs button:hover { color: var(--primary); }
  .tabs button.active { background: var(--bg); border-color: var(--border); color: var(--primary); font-weight: 600; }

  /* 内容区 */
  main { flex: 1 1 auto; min-height: 0; padding: 12px 20px; overflow: auto; }
  section.tab-page { display: none; height: 100%; }
  section.tab-page.active { display: flex; flex-direction: column; }

  .toolbar { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; flex: 0 0 auto; flex-wrap: wrap; }
  .toolbar .spacer { flex: 1 1 auto; }
  .btn { padding: 6px 14px; border: 1px solid var(--border); border-radius: 4px; background: #fff; color: var(--text); font-size: 13px; cursor: pointer; }
  .btn:hover { border-color: var(--primary); color: var(--primary); }
  .btn.primary { background: var(--primary); border-color: var(--primary); color: #fff; }
  .btn.primary:hover { opacity: .9; color: #fff; }
  .btn.ok { color: var(--ok); border-color: var(--ok); }
  .btn.danger { color: var(--danger); border-color: var(--danger); }
  .btn.warn { color: var(--warn); border-color: var(--warn); }
  .btn:disabled { opacity: .5; cursor: not-allowed; }

  table { width: 100%; border-collapse: collapse; background: #fff; font-size: 13px; }
  th, td { padding: 8px 10px; border-bottom: 1px solid var(--border); text-align: left; white-space: nowrap; }
  th { background: #fafafa; color: var(--text-2); font-weight: 500; }
  td.wrap { white-space: normal; }
  tr:hover td { background: #fafcff; }

  .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 12px; }
  .badge.gray  { background: #f0f2f5; color: var(--text-2); }
  .badge.blue  { background: var(--primary-bg); color: var(--primary); }
  .badge.green { background: #f0f9eb; color: var(--ok); }
  .badge.red   { background: #fef0f0; color: var(--danger); }
  .badge.orange{ background: #fdf6ec; color: var(--warn); }

  /* 双栏（流程设计） */
  .split { display: flex; gap: 12px; flex: 1 1 auto; min-height: 0; }
  .split .left { flex: 0 0 340px; overflow: auto; background: #fff; border: 1px solid var(--border); border-radius: 6px; }
  .split .right { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; background: #fff; border: 1px solid var(--border); border-radius: 6px; overflow: hidden; }
  .define-list { list-style: none; margin: 0; padding: 6px; }
  .define-list li { padding: 10px; border-radius: 4px; cursor: pointer; }
  .define-list li:hover { background: var(--bg); }
  .define-list li.active { background: var(--primary-bg); }
  .define-list .name { font-size: 14px; font-weight: 600; }
  .define-list .meta { font-size: 12px; color: var(--text-3); margin-top: 4px; display: flex; gap: 8px; align-items: center; }
  #designerFrame { flex: 1 1 auto; width: 100%; border: 0; }
  .designer-tip { padding: 40px; color: var(--text-3); text-align: center; }

  /* 卡片（发起流程） */
  .cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
  .card { background: #fff; border: 1px solid var(--border); border-radius: 6px; padding: 16px; cursor: pointer; }
  .card:hover, .card.active { border-color: var(--primary); box-shadow: 0 2px 8px rgba(48,104,236,.12); }
  .card h3 { margin: 0 0 6px; font-size: 15px; }
  .card p { margin: 0; font-size: 12px; color: var(--text-3); }
  .form-panel { background: #fff; border: 1px solid var(--border); border-radius: 6px; padding: 16px; margin-top: 12px; max-width: 640px; }
  .form-item { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; }
  .form-item label { flex: 0 0 130px; text-align: right; font-size: 13px; color: var(--text-2); line-height: 30px; }
  .form-item .input, .form-item select, .form-item textarea { flex: 1 1 auto; padding: 5px 10px; border: 1px solid var(--border); border-radius: 4px; font-size: 13px; font-family: inherit; }
  .form-item textarea { min-height: 60px; }
  .form-item .checks { flex: 1 1 auto; display: flex; flex-wrap: wrap; gap: 6px 14px; line-height: 30px; }
  .form-item .checks label { flex: 0 0 auto; }

  /* 弹层（详情） */
  .modal-mask { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 100; }
  .modal-mask.show { display: flex; align-items: center; justify-content: center; }
  .modal { width: min(1080px, 92vw); height: min(720px, 88vh); background: #fff; border-radius: 8px; display: flex; flex-direction: column; overflow: hidden; }
  .modal-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border); flex: 0 0 auto; }
  .modal-head h3 { margin: 0; font-size: 15px; }
  .modal-body { flex: 1 1 auto; min-height: 0; display: flex; overflow: hidden; }
  .timeline-box { flex: 0 0 380px; overflow: auto; padding: 14px; border-right: 1px solid var(--border); }
  .flow-box { flex: 1 1 auto; }
  .flow-box iframe { width: 100%; height: 100%; border: 0; }
  .timeline { list-style: none; margin: 0; padding: 0; }
  .timeline li { position: relative; padding: 0 0 16px 20px; border-left: 2px solid var(--border); margin-left: 6px; }
  .timeline li::before { content: ""; position: absolute; left: -7px; top: 2px; width: 12px; height: 12px; border-radius: 50%; background: #c0c4cc; }
  .timeline li.done::before { background: var(--ok); }
  .timeline li.doing::before { background: var(--primary); }
  .timeline li.cancel::before { background: var(--danger); }
  .timeline .t-title { font-size: 13px; font-weight: 600; }
  .timeline .t-meta { font-size: 12px; color: var(--text-3); margin-top: 4px; line-height: 1.6; }

  /* 监控统计 */
  .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 12px; }
  .stat { background: #fff; border: 1px solid var(--border); border-radius: 6px; padding: 14px; }
  .stat .num { font-size: 24px; font-weight: 700; }
  .stat .label { font-size: 12px; color: var(--text-3); margin-top: 4px; }

  .muted { color: var(--text-3); }
  .empty { padding: 40px; text-align: center; color: var(--text-3); background: #fff; border: 1px dashed var(--border); border-radius: 6px; }

  #toast { position: fixed; top: 60px; left: 50%; transform: translateX(-50%); z-index: 200; display: flex; flex-direction: column; gap: 8px; }
  .toast-item { padding: 10px 18px; border-radius: 6px; background: #333; color: #fff; font-size: 13px; box-shadow: 0 4px 12px rgba(0,0,0,.2); }
  .toast-item.ok { background: var(--ok); }
  .toast-item.err { background: var(--danger); }
</style>
</head>
<body>

<header class="topbar">
  <div class="brand">madong/workflow 工作流引擎演示 <small>PDO + 原生 SQL · iframe 嵌 visual-flow 设计器</small></div>
  <div class="user-switch">
    演示用户：
    <select id="userSelect"></select>
  </div>
</header>

<nav class="tabs" id="tabs">
  <button data-tab="design" class="active">流程设计</button>
  <button data-tab="launch">发起流程</button>
  <button data-tab="todo">待办事项</button>
  <button data-tab="done">已办事项</button>
  <button data-tab="mine">我的申请</button>
  <button data-tab="monitor">流程监控</button>
</nav>

<main>
  <!-- 1. 流程设计 -->
  <section class="tab-page active" id="tab-design">
    <div class="split">
      <div class="left">
        <div class="toolbar" style="padding: 8px 8px 0;">
          <button class="btn primary" id="btnNewDefine">新建定义</button>
          <button class="btn" id="btnReloadDefines">刷新</button>
        </div>
        <ul class="define-list" id="defineList"></ul>
      </div>
      <div class="right">
        <div class="toolbar" style="padding: 8px 10px; border-bottom: 1px solid var(--border); margin-bottom: 0;">
          <strong id="designerTitle" class="muted">未选中定义</strong>
          <div class="spacer"></div>
          <select id="designerMode">
            <option value="canvas">画布模式</option>
            <option value="dingtalk">钉钉模式</option>
          </select>
          <button class="btn" id="btnDesignSave" disabled>保存设计</button>
          <button class="btn ok" id="btnPublish" disabled>发布</button>
          <button class="btn" id="btnDesignPreview" disabled>预览</button>
        </div>
        <div id="designerHolder" style="flex:1 1 auto; min-height:0; display:flex;">
          <div class="designer-tip" style="flex:1;">← 从左侧选择一条流程定义开始设计</div>
        </div>
        <iframe id="designerFrame" style="display:none;" src=""></iframe>
      </div>
    </div>
  </section>

  <!-- 2. 发起流程 -->
  <section class="tab-page" id="tab-launch">
    <div class="toolbar"><strong>可用流程</strong><span class="muted">（仅显示已发布定义，点击卡片填写并发起）</span></div>
    <div class="cards" id="launchCards"></div>
    <div class="form-panel" id="launchForm" style="display:none;"></div>
  </section>

  <!-- 3. 待办事项 -->
  <section class="tab-page" id="tab-todo">
    <div class="toolbar">
      <strong>待办事项</strong>
      <span class="muted">当前用户：</span><span id="todoUser" class="muted"></span>
      <div class="spacer"></div>
      <button class="btn" id="btnReloadTodo">刷新</button>
    </div>
    <div id="todoList"></div>
  </section>

  <!-- 4. 已办事项 -->
  <section class="tab-page" id="tab-done">
    <div class="toolbar">
      <strong>已办事项</strong>
      <div class="spacer"></div>
      <button class="btn" id="btnReloadDone">刷新</button>
    </div>
    <div id="doneList"></div>
  </section>

  <!-- 5. 我的申请 -->
  <section class="tab-page" id="tab-mine">
    <div class="toolbar">
      <strong>我的申请</strong>
      <div class="spacer"></div>
      <button class="btn" id="btnReloadMine">刷新</button>
    </div>
    <div id="mineList"></div>
  </section>

  <!-- 6. 流程监控 -->
  <section class="tab-page" id="tab-monitor">
    <div class="toolbar">
      <strong>流程监控</strong>
      <div class="spacer"></div>
      <button class="btn" id="btnReloadMonitor">刷新</button>
    </div>
    <div id="monitorBody"></div>
  </section>
</main>

<div class="modal-mask" id="modalMask">
  <div class="modal">
    <div class="modal-head">
      <h3 id="modalTitle">实例详情</h3>
      <button class="btn" id="modalClose">关闭</button>
    </div>
    <div class="modal-body">
      <div class="timeline-box">
        <div id="instanceMeta"></div>
        <ul class="timeline" id="taskTimeline"></ul>
      </div>
      <div class="flow-box"><iframe id="viewerFrame" src=""></iframe></div>
    </div>
  </div>
</div>

<div id="toast"></div>

<script>
// ============ 基础 ============

const INSTANCE_STATES = { 0: ['运行中', 'blue'], 1: ['已完成', 'green'], 2: ['已拒绝', 'red'], 3: ['已挂起', 'orange'], 4: ['已撤回', 'gray'], 5: ['已作废', 'gray'] };
const TASK_STATES = { 0: ['待办', 'blue'], 1: ['已办结', 'green'], 2: ['已取消', 'gray'], 3: ['预留', 'gray'], 4: ['已挂起', 'orange'] };
const SUBMIT_TYPES = { agree: '同意', reject: '拒绝' };

// 发起表单模板（按种子流程预置；字段 key 即流程变量名）
const FORM_TEMPLATES = {
  'wf-leave': [
    { key: 'title',  label: '申请标题', type: 'text',   value: '请假申请' },
    { key: 'f_day',  label: '请假天数', type: 'number', value: 2, help: '小于 3 天走部门审批直达，大于等于 3 天走完整审批链' },
    { key: 'reason', label: '请假事由', type: 'textarea', value: '家中有事' },
  ],
  'wf-expense': [
    { key: 'title',    label: '申请标题',   type: 'text',   value: '差旅报销' },
    { key: 'f_amount', label: '报销金额(元)', type: 'number', value: 1500, help: '小于 1000 元审批直达，大于等于 1000 元走完整审批链' },
  ],
  'wf-fork-join': [
    { key: 'title', label: '申请标题', type: 'text', value: '并行任务申请' },
    { key: 'taskA', label: '分支A处理人', type: 'user', value: 'dept_manager' },
    { key: 'taskB', label: '分支B处理人', type: 'user', value: 'hr_manager' },
    { key: 'taskC', label: '分支C处理人', type: 'user', value: 'boss_manager' },
  ],
  'wf-countersign': [
    { key: 'title', label: '申请标题', type: 'text', value: '会签申请' },
    { key: 'countersign.operatorList', label: '会签人员(多选)', type: 'users', value: ['dept_manager', 'hr_manager'] },
  ],
  'wf-custom-node': [
    { key: 'title',    label: '申请标题',   type: 'text',   value: '订单金额计算' },
    { key: 'f_amount', label: '订单金额(元)', type: 'number', value: 2000, help: '自定义节点将计算含税金额并写入流程变量 calc_result' },
  ],
};

const state = {
  operator: localStorage.getItem('wf_demo_operator') || 'user1',
  users: [],
  defines: [],
  activeDefine: null,      // 设计器选中的定义
  designerReady: false,
  designerMode: 'canvas',
  launchDefine: null,      // 发起表单选中的定义
  viewerReady: false,
  viewerPayload: null,
};

function fmtTime(t) {
  if (!t || Number(t) <= 0) return '-';
  const d = new Date(Number(t) * 1000);
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`;
}

function badge(map, v) {
  const item = map[v] || ['未知', 'gray'];
  return `<span class="badge ${item[1]}">${item[0]}</span>`;
}

function toast(msg, type = '') {
  const el = document.createElement('div');
  el.className = 'toast-item ' + type;
  el.textContent = msg;
  document.getElementById('toast').appendChild(el);
  setTimeout(() => el.remove(), 2600);
}

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

// ============ 用户 ============

async function loadUsers() {
  state.users = await api('/users');
  const sel = document.getElementById('userSelect');
  sel.innerHTML = state.users.map(u => `<option value="${u.name}">${u.display_name}</option>`).join('');
  sel.value = state.operator;
}

function refreshAll() {
  document.getElementById('todoUser').textContent = userLabel(state.operator);
  loadDefines();
  loadLaunchCards();
  loadTodos();
  loadDones();
  loadMine();
  loadMonitor();
}

function userLabel(name) {
  const u = state.users.find(x => x.name === name);
  return u ? u.display_name : name;
}

document.getElementById('userSelect').addEventListener('change', (e) => {
  state.operator = e.target.value;
  localStorage.setItem('wf_demo_operator', state.operator);
  toast('已切换用户：' + userLabel(state.operator), 'ok');
  refreshAll();
});

// ============ 页签 ============

document.getElementById('tabs').addEventListener('click', (e) => {
  const btn = e.target.closest('button[data-tab]');
  if (!btn) return;
  document.querySelectorAll('#tabs button').forEach(b => b.classList.toggle('active', b === btn));
  document.querySelectorAll('section.tab-page').forEach(s => s.classList.toggle('active', s.id === 'tab-' + btn.dataset.tab));
});

// ============ 页签1：流程设计 ============

const designerFrame = document.getElementById('designerFrame');
const designerHolder = document.getElementById('designerHolder');

async function loadDefines() {
  state.defines = await api('/defines');
  const ul = document.getElementById('defineList');
  ul.innerHTML = state.defines.map(d => {
    const pub = Number(d.state) === 1;
    return `<li data-id="${d.id}" class="${state.activeDefine && state.activeDefine.id === d.id ? 'active' : ''}">
      <div class="name">${d.display_name || d.name}</div>
      <div class="meta">
        <code>${d.name}</code> v${d.version}
        ${pub ? '<span class="badge green">已发布</span>' : (Number(d.state) === 2 ? '<span class="badge red">已禁用</span>' : '<span class="badge gray">草稿</span>')}
        ${Number(d.is_active) === 1 ? '<span class="badge blue">活动版本</span>' : ''}
      </div>
    </li>`;
  }).join('') || '<li class="muted">暂无定义</li>';
}

document.getElementById('defineList').addEventListener('click', (e) => {
  const li = e.target.closest('li[data-id]');
  if (!li) return;
  selectDefine(state.defines.find(d => d.id === li.dataset.id));
});

document.getElementById('btnReloadDefines').addEventListener('click', loadDefines);

document.getElementById('btnNewDefine').addEventListener('click', async () => {
  const name = prompt('流程标识（英文，如 wf-demo）：');
  if (!name) return;
  const displayName = prompt('流程名称：', '演示流程') || name;
  const created = await api('/defines', { method: 'POST', body: { name, display_name: displayName, category: '演示流程' } });
  toast('创建成功', 'ok');
  await loadDefines();
  selectDefine(state.defines.find(d => d.id === created.id));
});

document.getElementById('designerMode').addEventListener('change', async (e) => {
  state.designerMode = e.target.value;
  if (state.activeDefine) {
    await openDesigner();
  }
});

async function openDesigner() {
  designerHolder.style.display = 'none';
  designerFrame.style.display = '';
  if (!designerFrame.src) {
    designerFrame.src = '/ui/index.html?mode=' + state.designerMode;
  }
  state.designerReady = false;
  // 模式切换需重建 iframe（组件 key 变化）
  designerFrame.src = '/ui/index.html?mode=' + state.designerMode + '&t=' + Date.now();
}

async function selectDefine(define) {
  state.activeDefine = define;
  document.getElementById('designerTitle').textContent = define ? (define.display_name || define.name) : '未选中定义';
  document.getElementById('btnDesignSave').disabled = !define;
  document.getElementById('btnPublish').disabled = !define;
  document.getElementById('btnDesignPreview').disabled = !define;
  loadDefines();
  if (define) {
    await openDesigner();
  }
}

async function pushToDesigner(payload) {
  designerFrame.contentWindow.postMessage(
    Object.assign({ type: 'flow.load' }, payload),
    '*'
  );
}

// iframe 消息：设计器就绪 / 保存
window.addEventListener('message', async (e) => {
  const msg = e.data;
  if (!msg || typeof msg !== 'object') return;

  if (msg.type === 'flow.ready') {
    if (e.source === designerFrame.contentWindow) {
      state.designerReady = true;
      if (state.activeDefine) {
        const detail = await api('/defines/' + state.activeDefine.id);
        pushToDesigner({ data: detail.content_json || {} });
      }
    } else if (e.source === document.getElementById('viewerFrame').contentWindow) {
      state.viewerReady = true;
      if (state.viewerPayload) {
        pushToViewer(state.viewerPayload);
      }
    }
    return;
  }

  if (msg.type === 'flow.save' && e.source === designerFrame.contentWindow) {
    if (!state.activeDefine) return;
    await api('/defines/' + state.activeDefine.id, { method: 'PUT', body: { content: msg.data } });
    toast('流程图已保存：' + state.activeDefine.name, 'ok');
    loadDefines();
  }
});

document.getElementById('btnDesignSave').addEventListener('click', () => {
  // 设计器控制面板的保存按钮触发 flow.save；此处直接请求设计器触发一次渲染保存不可行，
  // 提示用户点击画布控制面板的保存图标，或直接提示已通过 on-save 自动保存
  toast('请在画布右上角控制面板点击保存按钮（自动同步保存到后端）');
});

document.getElementById('btnPublish').addEventListener('click', async () => {
  if (!state.activeDefine) return;
  await api('/defines/' + state.activeDefine.id + '/publish', { method: 'POST' });
  toast('发布成功：' + state.activeDefine.name, 'ok');
  loadDefines();
  loadLaunchCards();
});

document.getElementById('btnDesignPreview').addEventListener('click', () => {
  if (!state.activeDefine) return;
  openInstanceModal({
    title: '预览：' + (state.activeDefine.display_name || state.activeDefine.name),
    instance: null,
    tasks: [],
    content: null,
    defineId: state.activeDefine.id,
    highLight: {},
  });
});

// ============ 页签2：发起流程 ============

async function loadLaunchCards() {
  const defines = await api('/defines');
  const published = defines.filter(d => Number(d.state) === 1);
  const box = document.getElementById('launchCards');
  box.innerHTML = published.map(d =>
    `<div class="card ${state.launchDefine && state.launchDefine.id === d.id ? 'active' : ''}" data-id="${d.id}">
      <h3>${d.display_name || d.name}</h3>
      <p><code>${d.name}</code> · v${d.version} · 发起人：${userLabel(state.operator)}</p>
    </div>`
  ).join('') || '<div class="empty">暂无已发布流程</div>';
}

document.getElementById('launchCards').addEventListener('click', (e) => {
  const card = e.target.closest('.card');
  if (!card) return;
  api('/defines').then(list => {
    state.defines = list;
    loadLaunchCards();
    showLaunchForm(list.find(d => d.id === card.dataset.id));
  });
});

function showLaunchForm(define) {
  state.launchDefine = define;
  const fields = FORM_TEMPLATES[define.name] || [
    { key: 'title', label: '申请标题', type: 'text', value: (define.display_name || define.name) + ' 申请' },
  ];
  const users = state.users.map(u => `<option value="${u.name}">${u.display_name}</option>`).join('');
  const html = fields.map(f => {
    if (f.type === 'user') {
      return `<div class="form-item"><label>${f.label}</label>
        <select data-key="${f.key}">${state.users.map(u => `<option value="${u.name}" ${u.name === f.value ? 'selected' : ''}>${u.display_name}</option>`).join('')}</select></div>`;
    }
    if (f.type === 'users') {
      const sel = Array.isArray(f.value) ? f.value : [];
      return `<div class="form-item"><label>${f.label}</label>
        <div class="checks">${state.users.map(u => `<label><input type="checkbox" data-key="${f.key}" value="${u.name}" ${sel.includes(u.name) ? 'checked' : ''}> ${u.display_name}</label>`).join('')}</div></div>`;
    }
    if (f.type === 'textarea') {
      return `<div class="form-item"><label>${f.label}</label><textarea data-key="${f.key}">${f.value ?? ''}</textarea></div>`;
    }
    return `<div class="form-item"><label>${f.label}</label>
      <input class="input" type="${f.type}" data-key="${f.key}" value="${f.value ?? ''}">
      ${f.help ? `<span class="muted" style="line-height:30px;font-size:12px;">${f.help}</span>` : ''}</div>`;
  }).join('');

  const panel = document.getElementById('launchForm');
  panel.style.display = '';
  panel.innerHTML = `
    <div class="toolbar"><strong>发起：${define.display_name || define.name}</strong><code>${define.name}</code></div>
    ${html}
    <div class="toolbar"><div class="spacer"></div>
      <button class="btn primary" id="btnSubmitLaunch">提 交 发 起</button>
    </div>`;

  document.getElementById('btnSubmitLaunch').onclick = async () => {
    const vars = { operator: state.operator };
    panel.querySelectorAll('[data-key]').forEach(el => {
      if (el.type === 'checkbox') {
        if (el.checked) {
          vars[el.dataset.key] = vars[el.dataset.key] || [];
          vars[el.dataset.key].push(el.value);
        }
      } else {
        vars[el.dataset.key] = el.type === 'number' ? Number(el.value) : el.value;
      }
    });
    try {
      const inst = await api('/instances', { method: 'POST', body: { defineId: define.id, operator: state.operator, vars } });
      toast('发起成功，实例号：' + String(inst.id).slice(-8), 'ok');
      loadMine();
      loadTodos();
      loadMonitor();
    } catch (err) {
      toast('发起失败：' + err.message, 'err');
    }
  };
}

// ============ 页签3：待办事项 ============

async function loadTodos() {
  const box = document.getElementById('todoList');
  try {
    const rows = await api('/todos?operator=' + encodeURIComponent(state.operator));
    if (!rows.length) {
      box.innerHTML = '<div class="empty">当前用户暂无待办</div>';
      return;
    }
    box.innerHTML = `<table>
      <thead><tr><th>任务</th><th>流程</th><th>发起人</th><th>到达时间</th><th style="width:280px;">办理</th></tr></thead>
      <tbody>${rows.map(t => {
        const m = TASK_STATES[t.task_state] || ['?', 'gray'];
        return `<tr data-task="${t.id}">
          <td><strong>${t.display_name || t.task_name}</strong> <code class="muted">${t.task_name}</code></td>
          <td>${t.instance_display_name || '-'}</td>
          <td>${userLabel(t.instance_operator) || '-'}</td>
          <td>${fmtTime(t.create_time)}</td>
          <td class="wrap">
            <input class="input todo-comment" placeholder="审批意见（可选）" style="width:150px;padding:4px 8px;">
            <button class="btn ok" data-action="agree">同意</button>
            <button class="btn warn" data-action="back">退回</button>
            <button class="btn danger" data-action="reject">拒绝</button>
          </td>
        </tr>`;
      }).join('')}</tbody></table>`;
  } catch (err) {
    box.innerHTML = `<div class="empty">${err.message}</div>`;
  }
}

document.getElementById('todoList').addEventListener('click', async (e) => {
  const btn = e.target.closest('button[data-action]');
  if (!btn) return;
  const tr = btn.closest('tr[data-task]');
  const taskId = tr.dataset.task;
  const action = btn.dataset.action;
  const comment = tr.querySelector('.todo-comment').value || '';
  if (!confirm(`确认${action === 'agree' ? '同意' : action === 'back' ? '退回发起人' : '拒绝终止'}该任务？`)) return;
  try {
    await api(`/tasks/${taskId}/complete`, { method: 'POST', body: { operator: state.operator, action, comment } });
    toast('办理成功', 'ok');
    loadTodos();
    loadDones();
    loadMine();
    loadMonitor();
  } catch (err) {
    toast('办理失败：' + err.message, 'err');
  }
});

document.getElementById('btnReloadTodo').addEventListener('click', loadTodos);

// ============ 页签4：已办事项 ============

async function loadDones() {
  const box = document.getElementById('doneList');
  try {
    const rows = await api('/dones?operator=' + encodeURIComponent(state.operator));
    if (!rows.length) {
      box.innerHTML = '<div class="empty">当前用户暂无已办</div>';
      return;
    }
    box.innerHTML = `<table>
      <thead><tr><th>任务</th><th>流程</th><th>提交类型</th><th>审批意见</th><th>办理时间</th><th>实例状态</th></tr></thead>
      <tbody>${rows.map(t => `<tr>
        <td><strong>${t.display_name || t.task_name}</strong></td>
        <td>${t.instance_display_name || '-'}</td>
        <td>${t.submit_type ? (SUBMIT_TYPES[t.submit_type] || t.submit_type) : '-'}</td>
        <td class="wrap">${t.comment || '-'}</td>
        <td>${fmtTime(t.finish_time)}</td>
        <td>${badge(INSTANCE_STATES, t.instance_state)}</td>
      </tr>`).join('')}</tbody></table>`;
  } catch (err) {
    box.innerHTML = `<div class="empty">${err.message}</div>`;
  }
}

document.getElementById('btnReloadDone').addEventListener('click', loadDones);

// ============ 页签5：我的申请 ============

async function loadMine() {
  const box = document.getElementById('mineList');
  try {
    const rows = await api('/instances?operator=' + encodeURIComponent(state.operator));
    if (!rows.length) {
      box.innerHTML = '<div class="empty">当前用户暂无申请</div>';
      return;
    }
    box.innerHTML = `<table>
      <thead><tr><th>标题</th><th>流程</th><th>状态</th><th>当前节点</th><th>发起时间</th><th>结束时间</th><th>操作</th></tr></thead>
      <tbody>${rows.map(i => {
        const nodes = (i.active_nodes || []).join('、') || '-';
        return `<tr>
          <td>${i.title || i.display_name}</td>
          <td>${i.display_name || i.define_name}</td>
          <td>${badge(INSTANCE_STATES, i.state)}</td>
          <td>${nodes}</td>
          <td>${fmtTime(i.create_time)}</td>
          <td>${fmtTime(i.end_time)}</td>
          <td><button class="btn" data-detail="${i.id}">详情</button></td>
        </tr>`;
      }).join('')}</tbody></table>`;
  } catch (err) {
    box.innerHTML = `<div class="empty">${err.message}</div>`;
  }
}

document.getElementById('mineList').addEventListener('click', async (e) => {
  const btn = e.target.closest('button[data-detail]');
  if (!btn) return;
  try {
    const detail = await api('/instances/' + btn.dataset.detail);
    await openInstanceModal({
      title: '实例详情：' + (detail.instance.title || detail.instance.display_name),
      instance: detail.instance,
      tasks: detail.tasks,
      historyNodeNames: detail.history_node_names,
      activeNodeNames: detail.active_node_names,
      defineId: detail.instance.process_define_id,
    });
  } catch (err) {
    toast(err.message, 'err');
  }
});

document.getElementById('btnReloadMine').addEventListener('click', loadMine);

// ============ 详情弹层（轨迹 + 流程图高亮） ============

const viewerFrame = document.getElementById('viewerFrame');
const modalMask = document.getElementById('modalMask');

function pushToViewer(payload) {
  viewerFrame.contentWindow.postMessage(Object.assign({ type: 'flow.load' }, payload), '*');
}

async function openInstanceModal(opts) {
  document.getElementById('modalTitle').textContent = opts.title;
  modalMask.classList.add('show');

  // 轨迹时间线
  const meta = document.getElementById('instanceMeta');
  const tl = document.getElementById('taskTimeline');
  if (opts.instance) {
    meta.innerHTML = `<p style="font-size:13px;color:var(--text-2);">${badge(INSTANCE_STATES, opts.instance.state)}
      发起人：${userLabel(opts.instance.operator)} · 发起时间：${fmtTime(opts.instance.create_time)}</p>`;
    tl.innerHTML = opts.tasks.map(t => {
      const st = Number(t.task_state);
      const cls = st === 1 ? 'done' : st === 0 ? 'doing' : 'cancel';
      const stBadge = badge(TASK_STATES, t.task_state);
      const actors = (t.actors || []).map(userLabel).join('、') || '-';
      const comment = t.comment ? ` · 意见：${t.comment}` : '';
      const submit = t.submit_type ? ` · ${SUBMIT_TYPES[t.submit_type] || t.submit_type}` : '';
      return `<li class="${cls}">
        <div class="t-title">${t.display_name || t.task_name} ${stBadge}</div>
        <div class="t-meta">处理人：${actors}<br>${fmtTime(t.create_time)}${st === 1 ? ' → ' + fmtTime(t.finish_time) : ''}${submit}${comment}</div>
      </li>`;
    }).join('') || '<li><div class="t-meta muted">暂无任务轨迹</div></li>';
  } else {
    meta.innerHTML = '';
    tl.innerHTML = '<li><div class="t-meta muted">预览模式（无运行轨迹）</div></li>';
  }

  // 流程图（viewer 模式 + 高亮）
  state.viewerReady = false;
  state.viewerPayload = null;
  const content = opts.content !== undefined
    ? opts.content
    : (await api('/defines/' + opts.defineId)).content_json;
  const payload = {
    data: content || {},
    viewer: true,
    highLight: {
      history_node_names: opts.historyNodeNames || [],
      active_node_names: opts.activeNodeNames || [],
    },
  };
  if (!viewerFrame.src || viewerFrame.dataset.defineId !== String(opts.defineId)) {
    viewerFrame.src = '/ui/index.html?viewer=1&t=' + Date.now();
    viewerFrame.dataset.defineId = opts.defineId || '';
  }
  state.viewerPayload = payload;
  if (state.viewerReady) {
    pushToViewer(payload);
  }
}

document.getElementById('modalClose').addEventListener('click', () => {
  modalMask.classList.remove('show');
  viewerFrame.src = '';
  viewerFrame.dataset.defineId = '';
});

// ============ 页签6：流程监控 ============

async function loadMonitor() {
  const body = document.getElementById('monitorBody');
  try {
    const data = await api('/monitor/overview');
    const iMap = {};
    data.instance_states.forEach(r => { iMap[r.state] = Number(r.c); });
    const tMap = {};
    data.task_states.forEach(r => { tMap[r.task_state] = Number(r.c); });
    const sum = (m) => Object.values(m).reduce((a, b) => a + b, 0);

    const stats = `
      <div class="stats">
        <div class="stat"><div class="num">${sum(iMap)}</div><div class="label">实例总数</div></div>
        <div class="stat"><div class="num" style="color:var(--primary)">${iMap[0] || 0}</div><div class="label">运行中</div></div>
        <div class="stat"><div class="num" style="color:var(--ok)">${iMap[1] || 0}</div><div class="label">已完成</div></div>
        <div class="stat"><div class="num" style="color:var(--danger)">${iMap[2] || 0}</div><div class="label">已拒绝</div></div>
        <div class="stat"><div class="num">${tMap[0] || 0}</div><div class="label">待办任务</div></div>
        <div class="stat"><div class="num" style="color:var(--ok)">${tMap[1] || 0}</div><div class="label">已办结任务</div></div>
      </div>`;

    const rows = data.running || [];
    const table = rows.length ? `<table>
      <thead><tr><th>标题</th><th>流程</th><th>发起人</th><th>发起时间</th><th>操作</th></tr></thead>
      <tbody>${rows.map(i => `<tr>
        <td>${i.title || i.display_name}</td>
        <td>${i.display_name || i.define_name}</td>
        <td>${userLabel(i.operator)}</td>
        <td>${fmtTime(i.create_time)}</td>
        <td><button class="btn" data-detail="${i.id}">轨迹</button></td>
      </tr>`).join('')}</tbody></table>` : '<div class="empty">暂无运行中实例</div>';

    body.innerHTML = stats + '<div class="toolbar"><strong>运行中实例</strong></div>' + table;
    body.querySelectorAll('button[data-detail]').forEach(btn => {
      btn.addEventListener('click', () => showInstanceDetail(btn.dataset.detail));
    });
  } catch (err) {
    body.innerHTML = `<div class="empty">${err.message}</div>`;
  }
}

async function showInstanceDetail(id) {
  try {
    const detail = await api('/instances/' + id);
    await openInstanceModal({
      title: '实例详情：' + (detail.instance.title || detail.instance.display_name),
      instance: detail.instance,
      tasks: detail.tasks,
      historyNodeNames: detail.history_node_names,
      activeNodeNames: detail.active_node_names,
      defineId: detail.instance.process_define_id,
    });
  } catch (err) {
    toast(err.message, 'err');
  }
}

document.getElementById('btnReloadMonitor').addEventListener('click', loadMonitor);

// ============ 启动 ============

(async function init() {
  await loadUsers();
  refreshAll();
})().catch(err => toast('初始化失败：' + err.message, 'err'));
</script>
</body>
</html>
