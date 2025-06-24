# ingenious 流程引擎

## 介绍
ingenious 是一个基于 PHP 的流程引擎，提供了一套完整的流程管理解决方案。它支持流程定义、执行、监控和优化等功能，适用于各种业务场景。

## 核心功能
- **流程定义**：通过图形化界面或配置文件定义流程模型。
- **流程执行**：支持流程实例的启动、任务处理和流程推进。
- **流程监控**：提供流程实例和任务的状态跟踪。
- **流程优化**：支持流程性能分析和优化建议。

## 适配 v2-demo
本项目已适配 v2-demo，确保与最新版本的兼容性和稳定性。

## 主要特性
- **模块化设计**：各组件解耦，便于扩展和维护。
- **丰富的接口**：提供多种接口支持不同的业务需求。
- **灵活的配置**：支持多种配置方式，便于集成到现有系统。
- **高性能**：优化的流程执行引擎，确保高效处理大量流程实例。

## 在线体验
[在线演示地址](https://demo.ingenious.com)

## 安装
### 依赖环境
- PHP 7.4 或更高版本
- MySQL 5.6 或更高版本
- Composer

### 安装步骤
1. 克隆仓库：
   ```bash
   git clone https://gitee.com/madong/ingenious.git
   ```
2. 安装依赖：
   ```bash
   cd ingenious
   composer install
   ```
3. 配置数据库：
   ```bash
   mysql -u root -p < install.sql
   ```
4. 配置文件：
   修改 `src/cfg/Configuration.php` 中的数据库连接信息。

5. 启动服务：
   ```bash
   php -S localhost:8000
   ```

## 链接
- [GitHub 仓库](https://github.com/madong/ingenious)
- [文档](https://ingenious.com/docs)
- [社区](https://ingenious.com/community)