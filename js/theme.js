// 主题管理器
class ThemeManager {
    constructor() {
        this.themes = {
            dark: {
                name: 'dark',
                icon: '🌙',
                label: '深色模式'
            },
            light: {
                name: 'light', 
                icon: '☀️',
                label: '浅色模式'
            },
            system: {
                name: 'system',
                icon: '🖥️',
                label: '跟随系统'
            }
        };
        
        this.init();
    }
    
    init() {
        // 从localStorage加载保存的主题
        const savedTheme = localStorage.getItem('theme') || 'system';
        this.applyTheme(savedTheme);
        this.createThemeToggle();
    }
    
    applyTheme(themeName) {
        const theme = this.themes[themeName] || this.themes.system;
        const isSystemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        let effectiveTheme = themeName;
        if (themeName === 'system') {
            effectiveTheme = isSystemDark ? 'dark' : 'light';
        }
        
        // 设置HTML属性
        document.documentElement.setAttribute('data-theme', effectiveTheme);
        document.documentElement.setAttribute('data-theme-mode', themeName);
        
        // 保存到localStorage
        localStorage.setItem('theme', themeName);
        
        // 更新切换按钮
        this.updateToggleButton(theme);
        
        // 触发主题变更事件
        this.triggerThemeChange(effectiveTheme, themeName);
    }
    
    createThemeToggle() {
        // 查找现有的主题切换按钮
        let toggleBtn = document.getElementById('themeToggle');
        
        if (!toggleBtn) {
            // 创建主题切换按钮
            toggleBtn = document.createElement('button');
            toggleBtn.id = 'themeToggle';
            toggleBtn.className = 'btn btn-sm btn-outline theme-toggle';
            toggleBtn.innerHTML = '🌙 主题';
            toggleBtn.setAttribute('aria-label', '切换主题');
            
            // 添加到页面
            const header = document.querySelector('.sidebar-actions, .admin-header, .auth-header');
            if (header) {
                header.prepend(toggleBtn);
            } else {
                // 如果找不到合适的容器，添加到body
                document.body.insertAdjacentElement('afterbegin', toggleBtn);
            }
            
            // 添加点击事件
            toggleBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.showThemeMenu(toggleBtn);
            });
        }
    }
    
    showThemeMenu(button) {
        // 移除现有的菜单
        const existingMenu = document.getElementById('themeMenu');
        if (existingMenu) {
            existingMenu.remove();
            return;
        }
        
        // 创建菜单
        const menu = document.createElement('div');
        menu.id = 'themeMenu';
        menu.className = 'theme-menu';
        
        // 添加菜单项
        Object.values(this.themes).forEach(theme => {
            const item = document.createElement('button');
            item.className = 'theme-menu-item';
            item.innerHTML = `${theme.icon} ${theme.label}`;
            if (localStorage.getItem('theme') === theme.name) {
                item.classList.add('active');
            }
            item.addEventListener('click', () => {
                this.applyTheme(theme.name);
                menu.remove();
            });
            menu.appendChild(item);
        });
        
        // 定位菜单
        const rect = button.getBoundingClientRect();
        menu.style.position = 'fixed';
        menu.style.top = `${rect.bottom + 5}px`;
        menu.style.left = `${rect.left}px`;
        menu.style.zIndex = '1001';
        
        document.body.appendChild(menu);
        
        // 点击外部关闭菜单
        const closeMenu = (e) => {
            if (!menu.contains(e.target) && e.target !== button) {
                menu.remove();
                document.removeEventListener('click', closeMenu);
            }
        };
        
        setTimeout(() => {
            document.addEventListener('click', closeMenu);
        }, 0);
    }
    
    updateToggleButton(theme) {
        const toggleBtn = document.getElementById('themeToggle');
        if (toggleBtn) {
            toggleBtn.innerHTML = `${theme.icon}`;
            toggleBtn.title = theme.label;
        }
    }
    
    triggerThemeChange(effectiveTheme, themeMode) {
        const event = new CustomEvent('themechange', {
            detail: { effectiveTheme, themeMode }
        });
        document.dispatchEvent(event);
    }
    
    // 监听系统主题变化
    watchSystemTheme() {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addEventListener('change', (e) => {
            if (localStorage.getItem('theme') === 'system') {
                this.applyTheme('system');
            }
        });
    }
}

// 初始化主题管理器
const themeManager = new ThemeManager();

// 监听系统主题变化
themeManager.watchSystemTheme();