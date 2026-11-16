/**
 * madong workflow demo · 实例详情右侧抽屉（共享组件，Tabs 形态）
 *
 * 右侧滑入抽屉，三个页签（对齐 jeeflow 详情抽屉）：
 *   详情     = 实例元信息 + 申请信息（表单字段展示）+ 抄送按钮
 *   流程图   = visual-flow viewer（已走/当前节点高亮）+ 钉钉/画布模式切换
 *   审批记录 = 任务轨迹时间线
 * 流程图 tab 首次激活时才重建 viewer iframe（容器可见，避免画布宽度为 0）。
 * 使用方：designer 预览 / todo / done / mine / monitor / cc 详情。
 * postMessage 协议不变（flow.ready / flow.load）。
 */
window.DetailDrawer = (function () {
  'use strict';

  const A = window.App;

  const MODES = [
    { key: 'dingtalk', label: '钉钉模式' },
    { key: 'canvas', label: '画布模式' },
  ];

  let viewerFrame = null;
  let viewerReady = false;
  let viewerPayload = null;
  let viewerStarted = false;   // 流程图 tab 首次激活标记
  let mode = localStorage.getItem('wf_viewer_mode') === 'canvas' ? 'canvas' : 'dingtalk';
  let ccInstance = null;       // 抄送目标实例 id
  let inited = false;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function ensureDom() {
    if (inited) return;
    inited = true;

    const wrap = document.createElement('div');
    wrap.innerHTML =
      '<div class="drawer-mask" id="detailDrawerMask"><aside class="drawer">'
      + '<div class="drawer-head">'
      + '<h3 id="drawerTitle">实例详情</h3>'
      + '<button class="modal-close" id="drawerClose" title="关闭">&times;</button>'
      + '</div>'
      + '<div class="drawer-tabs" id="drawerTabs">'
      + '<button class="dtab active" data-tab="form">详情</button>'
      + '<button class="dtab" data-tab="flow">流程图</button>'
      + '<button class="dtab" data-tab="record">审批记录</button>'
      + '</div>'
      + '<div class="drawer-body">'
      + '<div class="dpanel active" data-panel="form">'
      + '<div id="drawerMeta"></div>'
      + '<div id="drawerForm"></div>'
      + '<div class="drawer-actions" id="drawerActions"></div>'
      + '</div>'
      + '<div class="dpanel" data-panel="flow">'
      + '<div class="flow-toolbar"><div class="mode-switch" id="drawerModeSwitch"></div></div>'
      + '<div class="flow-box"><iframe id="drawerViewerFrame" src=""></iframe></div>'
      + '</div>'
      + '<div class="dpanel" data-panel="record"><ul class="timeline" id="drawerTimeline"></ul></div>'
      + '</div>'
      + '</aside></div>'
      // 抄送弹窗（复用居中模态风格，层级高于抽屉）
      + '<div class="modal-mask" id="ccModal" style="z-index:1400;">'
      + '<div class="modal" style="width:min(420px,92vw);">'
      + '<div class="modal-head"><h3>抄送给</h3><button class="modal-close" id="ccClose" title="关闭">&times;</button></div>'
      + '<div class="modal-body"><div class="checks" id="ccUsers"></div></div>'
      + '<div class="modal-foot"><button class="btn" id="ccCancel">取消</button>'
      + '<button class="btn primary" id="ccSubmit">确认抄送</button></div>'
      + '</div></div>';
    document.body.appendChild(wrap.firstElementChild);
    document.body.appendChild(wrap.lastElementChild);

    viewerFrame = document.getElementById('drawerViewerFrame');

    document.getElementById('drawerClose').addEventListener('click', close);
    document.getElementById('detailDrawerMask').addEventListener('click', (e) => {
      if (e.target.id === 'detailDrawerMask') close();
    });

    // 页签切换；流程图首次激活时才启动 viewer（容器可见后重建，避免宽 0）
    document.getElementById('drawerTabs').addEventListener('click', (e) => {
      const btn = e.target.closest('.dtab[data-tab]');
      if (!btn || btn.classList.contains('active')) return;
      document.querySelectorAll('#drawerTabs .dtab').forEach((b) => b.classList.toggle('active', b === btn));
      const tab = btn.dataset.tab;
      document.querySelectorAll('#detailDrawerMask .dpanel').forEach((p) => {
        p.classList.toggle('active', p.dataset.panel === tab);
      });
      if (tab === 'flow' && !viewerStarted) rebuildViewer();
    });

    document.getElementById('drawerModeSwitch').addEventListener('click', (e) => {
      const btn = e.target.closest('.ms-btn[data-mode]');
      if (!btn || btn.dataset.mode === mode) return;
      mode = btn.dataset.mode;
      localStorage.setItem('wf_viewer_mode', mode);
      renderModeSwitch();
      rebuildViewer();   // 切换模式 → 按 mode 参数重建 viewer iframe
    });

    // 抄送弹窗
    document.getElementById('drawerActions').addEventListener('click', (e) => {
      if (!e.target.closest('#btnCc') || !ccInstance) return;
      document.getElementById('ccUsers').innerHTML = A.state.users.map((u) =>
        '<label><input type="checkbox" value="' + esc(u.name) + '"> ' + esc(u.display_name) + '</label>'
      ).join('');
      document.getElementById('ccModal').classList.add('show');
    });
    document.getElementById('ccClose').addEventListener('click', closeCc);
    document.getElementById('ccCancel').addEventListener('click', closeCc);
    document.getElementById('ccModal').addEventListener('click', (e) => {
      if (e.target.id === 'ccModal') closeCc();
    });
    document.getElementById('ccSubmit').addEventListener('click', submitCc);

    // viewer iframe 就绪后推送高亮数据
    window.addEventListener('message', (e) => {
      const msg = e.data;
      if (!msg || typeof msg !== 'object') return;
      if (msg.type === 'flow.ready' && viewerFrame && e.source === viewerFrame.contentWindow) {
        viewerReady = true;
        if (viewerPayload) pushToViewer(viewerPayload);
      }
    });
  }

  function closeCc() {
    document.getElementById('ccModal').classList.remove('show');
  }

  async function submitCc() {
    if (!ccInstance) return;
    const operators = Array.from(document.querySelectorAll('#ccUsers input:checked')).map((el) => el.value);
    if (!operators.length) {
      A.message('请选择被抄送人', 'error');
      return;
    }
    try {
      const res = await A.api('/instances/' + ccInstance + '/cc', { method: 'POST', body: { operators } });
      closeCc();
      A.message('抄送成功（新增 ' + (res.cc_inserted ?? operators.length) + ' 人）', 'success');
    } catch (err) {
      A.message('抄送失败：' + err.message, 'error');
    }
  }

  function renderModeSwitch() {
    document.getElementById('drawerModeSwitch').innerHTML = MODES.map((m) =>
      '<button class="ms-btn ' + (m.key === mode ? 'active' : '') + '" data-mode="' + m.key + '">' + m.label + '</button>'
    ).join('');
  }

  /** 按 mode 参数重建 viewer iframe（组件完全重置，确保高亮与模式干净生效） */
  function rebuildViewer() {
    if (!viewerFrame) return;
    viewerStarted = true;
    viewerReady = false;
    viewerFrame.src = '/assets/ui/index.html?viewer=1&mode=' + mode + '&t=' + Date.now();
  }

  function pushToViewer(payload) {
    if (viewerFrame && viewerFrame.contentWindow) {
      viewerFrame.contentWindow.postMessage(Object.assign({ type: 'flow.load', mode }, payload), '*');
    }
  }

  /** 详情页签：实例元信息 + 申请信息（表单字段网格）+ 抄送动作 */
  function renderFormTab(opts) {
    const meta = document.getElementById('drawerMeta');
    const form = document.getElementById('drawerForm');
    const actions = document.getElementById('drawerActions');

    if (opts.instance) {
      meta.innerHTML = '<p class="d-meta">'
        + A.badge(A.INSTANCE_STATES, opts.instance.state)
        + ' <strong>' + esc(opts.instance.title || opts.instance.display_name || '-') + '</strong>'
        + '<span class="d-meta-sub">发起人：' + esc(A.userLabel(opts.instance.operator))
        + ' · 时间：' + A.fmtTime(opts.instance.create_time) + '</span></p>';

      const fields = opts.formFields || [];
      form.innerHTML = '<h4 class="d-section">申请信息</h4>'
        + (fields.length
          ? '<div class="d-form-grid">' + fields.map((f) =>
              '<div class="d-field"><span class="d-label">' + esc(f.label) + '</span>'
              + '<span class="d-value" title="' + esc(f.value) + '">' + esc(f.value) + '</span></div>').join('')
            + '</div>'
          : '<div class="muted" style="font-size:13px;">暂无表单字段</div>');

      ccInstance = String(opts.instance.id);
      actions.innerHTML = '<button class="btn" id="btnCc">抄送</button>';
    } else {
      meta.innerHTML = '';
      form.innerHTML = '<div class="muted" style="font-size:13px;">预览模式（无申请信息）</div>';
      actions.innerHTML = '';
      ccInstance = null;
    }
  }

  /** 审批记录页签：任务轨迹时间线 */
  function renderRecordTab(opts) {
    const tl = document.getElementById('drawerTimeline');
    if (!opts.instance) {
      tl.innerHTML = '<li><div class="t-meta muted">预览模式（无运行轨迹）</div></li>';
      return;
    }
    tl.innerHTML = (opts.tasks || []).map((t) => {
      const st = Number(t.task_state);
      const cls = st === 1 ? 'done' : st === 0 ? 'doing' : 'cancel';
      const actors = (t.actors || []).map(A.userLabel).join('、') || '-';
      const comment = t.comment ? ' · 意见：' + esc(t.comment) : '';
      const submit = t.submit_type ? ' · ' + (A.SUBMIT_TYPES[t.submit_type] || t.submit_type) : '';
      return '<li class="' + cls + '">'
        + '<div class="t-title">' + esc(t.display_name || t.task_name) + ' ' + A.badge(A.TASK_STATES, t.task_state) + '</div>'
        + '<div class="t-meta">处理人：' + esc(actors) + '<br>' + A.fmtTime(t.create_time)
        + (st === 1 ? ' → ' + A.fmtTime(t.finish_time) : '') + submit + comment + '</div>'
        + '</li>';
    }).join('') || '<li><div class="t-meta muted">暂无审批记录</div></li>';
  }

  /**
   * 打开详情抽屉（默认停在「详情」页签）
   * @param {Object} opts {
   *   title, instance, tasks, historyNodeNames, activeNodeNames, defineId, content,
   *   formFields: [{label,value}]  申请信息字段（列表页用 FormTemplates.describe 组装）
   * }
   */
  async function open(opts) {
    ensureDom();
    renderModeSwitch();
    document.getElementById('drawerTitle').textContent = opts.title || '实例详情';

    // 流程图内容：预览直接携带 content，实例详情按 defineId 拉取。
    // 先取数据再亮抽屉：mask.show 之后不再有 await，避免「抽屉已开 → 用户切到流程图页签
    // 触发 rebuildViewer，open 恢复执行又 rebuildViewer」的 iframe 双重加载竞争。
    viewerPayload = null;
    const content = opts.content !== undefined
      ? opts.content
      : (opts.defineId ? (await A.api('/defines/' + opts.defineId)).content_json : null);
    viewerPayload = {
      data: content || {},
      viewer: true,
      highLight: {
        history_node_names: opts.historyNodeNames || [],
        active_node_names: opts.activeNodeNames || [],
        history_edge_names: opts.historyEdgeNames || [],
      },
    };

    document.getElementById('detailDrawerMask').classList.add('show');

    // 重置到详情页签（iframe 若已启动则保留，避免重复加载）
    const firstTab = document.querySelector('#drawerTabs .dtab[data-tab="form"]');
    if (firstTab && !firstTab.classList.contains('active')) firstTab.click();
    renderFormTab(opts);
    renderRecordTab(opts);
    if (viewerStarted) rebuildViewer();
    if (viewerReady) pushToViewer(viewerPayload);
  }

  /**
   * 列表页共享：拉取实例详情并打开抽屉（自动组装 formFields 与标题）
   * todo / done / mine / monitor / cc 均调用此方法，禁止重复粘贴
   */
  async function openInstance(id) {
    const d = await A.api('/instances/' + id);
    const inst = d.instance;
    const stateText = (A.INSTANCE_STATES[inst.state] || ['-'])[0];
    return open({
      title: (inst.display_name || inst.define_name) + ' · ' + stateText,
      instance: inst,
      tasks: d.tasks,
      historyNodeNames: d.history_node_names,
      activeNodeNames: d.active_node_names,
      historyEdgeNames: d.history_edge_names,
      defineId: inst.process_define_id,
      formFields: window.FormTemplates.describe(inst.define_name, inst.variable_json),
    });
  }

  function close() {
    const mask = document.getElementById('detailDrawerMask');
    if (mask) mask.classList.remove('show');
    if (viewerFrame) viewerFrame.src = 'about:blank';
    viewerReady = false;
    viewerPayload = null;
    viewerStarted = false;
    ccInstance = null;
    closeCc();
  }

  return { open, openInstance, close };
})();
