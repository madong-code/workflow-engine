/**
 * madong workflow demo · 发起表单模板（共享，禁止复制粘贴）
 *
 * launch（发起表单渲染）与 detail-drawer（申请信息展示）共用。
 * 字段 key 即流程变量名；type: text/number/textarea/user/users。
 */
window.FormTemplates = (function () {
  'use strict';

  // 发起表单模板（按种子流程预置；值即发起表单默认值）
  const TEMPLATES = {
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

  const FALLBACK = [
    { key: 'title', label: '申请标题', type: 'text' },
  ];

  /** 发起表单字段（define.name 匹配模板，未匹配回退通用标题字段） */
  function fieldsFor(define) {
    return TEMPLATES[define && define.name] || FALLBACK;
  }

  /**
   * 详情展示：把实例变量按模板字段格式化为 [{label, value}]（值空显示 -）
   * user/users 类型把账号转换为显示名
   */
  function describe(defineName, vars) {
    const A = window.App;
    const v = vars && typeof vars === 'object' ? vars : {};
    return (TEMPLATES[defineName] || FALLBACK).map((f) => {
      let value = v[f.key];
      if (f.type === 'user') {
        value = A.userLabel(value);
      } else if (f.type === 'users') {
        value = Array.isArray(value) ? value.map((x) => A.userLabel(x)).join('、') : '';
      }
      if (value === undefined || value === null || String(value) === '') value = '-';
      return { label: f.label, value: String(value) };
    });
  }

  return { TEMPLATES, fieldsFor, describe };
})();
