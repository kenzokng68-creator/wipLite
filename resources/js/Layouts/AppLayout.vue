<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Link, usePage, Head, router } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import {
  LayoutDashboard,
  Users,
  Megaphone,
  Calendar,
  Clock,
  BarChart3,
  UserPlus,
  GitBranch,
  Shield,
  Bell,
  User,
  TreePine,
  ChevronDown,
  LogOut,
  UserCircle
} from 'lucide-vue-next';

const page = usePage();
const activeMainMenu = ref('dashboard');
const isHoveringSidebar = ref(false);

const findActiveMenu = () => {
  const currentPath = page.url.split('?')[0];
  const role = page.props.auth?.role;
  const config = menuConfig[role] ?? menuConfig.tc;

  // Chercher d'abord une correspondance exacte dans les sous-menus
  for (const [menuId, subMenus] of Object.entries(config.sub)) {
    if (subMenus.some(sub => sub.href === currentPath)) {
      activeMainMenu.value = menuId;
      return;
    }
  }

  // Si pas de correspondance exacte, chercher par préfixe (pour les routes de création/édition par ex)
  for (const [menuId, subMenus] of Object.entries(config.sub)) {
    if (subMenus.some(sub => {
      if (sub.href === '/') return currentPath === '/';
      return currentPath.startsWith(sub.href + '/') || currentPath === sub.href;
    })) {
      activeMainMenu.value = menuId;
      return;
    }
  }
};

onMounted(findActiveMenu);
watch(() => page.url, findActiveMenu);

 const menuConfig = {


  // ─── ADMIN ───────────────────────────────────────────────

  admin: {

    main: [

      { id: 'dashboard',    label: 'Dashboard',              icon: LayoutDashboard },

      { id: 'employees',    label: 'Gestion des employés',   icon: UserPlus },

      { id: 'campaigns',    label: 'Gestion des campagnes',  icon: Megaphone },

      { id: 'assignments',  label: 'Gestion des affectations', icon: GitBranch },

      { id: 'planning',     label: 'Gestion des plannings',  icon: Calendar },

      { id: 'timesheets',   label: 'Gestion des heures',     icon: Clock },

      { id: 'security',     label: 'Utilisateurs & Sécurité',icon: Shield },

      { id: 'reports',      label: 'Rapports',               icon: BarChart3 },

      { id: 'account',      label: 'Mon compte',             icon: User },

    ],

    sub: {

      dashboard: [

        { label: 'Tableau de bord',        href: '/dashboard/admin' },

        { label: 'Statistiques générales', href: '/dashboard/admin/stats' },

        { label: 'Alertes & notifications',href: '/dashboard/admin/alerts' },

      ],

      employees: [

        { label: 'Liste des employés',     href: '/employees' },

        { label: 'Historique des employés',href: '/employees/history' },

      ],

      campaigns: [

        { label: 'Liste des campagnes',    href: '/campaigns' },

        { label: 'Campagnes actives',      href: '/active/campaigns' },

        { label: 'Campagnes inactives',    href: '/inactive/campaigns' },

      ],

      assignments: [

        { label: 'Affectation CP → Campagne', href: '/assign/cp' },

        { label: 'Affectation SUP → CP',      href: '/assign/sup' },

        { label: 'Affectation TC → SUP',      href: '/assign/tc' },

        // { label: 'Vue hiérarchique',          href: '/assignments/hierarchy' },

        // { label: 'Réaffectations',            href: '/assignments/reassign' },

        { label: 'Historique des affectations',href: '/assignments/history' },

      ],

      planning: [

        { label: 'Modèles de planning',    href: '/planning/models' },

        { label: 'Affectation des plannings', href: '/planning/assignments' },

        { label: 'Validation des plannings',  href: '/planning/validate' },

        { label: 'Historique des plannings',  href: '/planning/history' },

      ],

      timesheets: [

        { label: 'Saisie des heures',      href: '/timesheets' },

        { label: 'Validation des heures',  href: '/timesheets/validate' },

        { label: 'Historique des heures',  href: '/timesheets/history' },

        { label: 'Rapport des écarts',     href: '/timesheets/gaps' },

      ],

      security: [

        { label: 'Comptes utilisateurs',   href: '/users' },

        { label: 'Rôles & permissions',    href: '/users/roles' },

        { label: 'Journal d\'activité',    href: '/activity-logs' },

        { label: 'Paramètres système',     href: '/settings' },

      ],

      reports: [

        { label: 'Rapport RH',             href: '/reports/hr' },

        { label: 'Rapport campagnes',      href: '/reports/campaigns' },

        { label: 'Rapport affectations',   href: '/reports/assignments' },

        { label: 'Rapport heures travaillées', href: '/reports/timesheets' },

      ],

      account: [

        { label: 'Profil',                 href: '/profile' },

        { label: 'Modifier mot de passe',  href: '/profile/password' },

      ],

    },

  },


  // ─── CHEF DE PLATEAU ─────────────────────────────────────

  cp: {

    main: [

      { id: 'dashboard',    label: 'Dashboard',                  icon: LayoutDashboard },

      { id: 'campaigns',    label: 'Mes campagnes',              icon: Megaphone },

      { id: 'supervisors',  label: 'Gestion des superviseurs',   icon: Users },

      { id: 'teleconseillers', label: 'Gestion des téléconseillers', icon: UserPlus },

      { id: 'hierarchy',    label: 'Vue hiérarchique',           icon: TreePine },

      { id: 'planning',     label: 'Gestion des plannings',      icon: Calendar },

      { id: 'timesheets',   label: 'Gestion des heures',         icon: Clock },

      { id: 'reports',      label: 'Rapports',                   icon: BarChart3 },

      { id: 'account',      label: 'Mon compte',                 icon: User },

    ],

    sub: {

      dashboard: [

        { label: 'Tableau de bord',  href: '/dashboard/cp' },

        { label: 'Notifications',    href: '/notifications' },

      ],

      campaigns: [

        { label: 'Campagnes assignées',  href: '/campaigns' },

        { label: 'Détails des campagnes',href: '/campaigns/details' },

      ],

      supervisors: [

        { label: 'Liste des superviseurs', href: '/supervisors' },

        { label: 'Affecter un superviseur',href: '/assignments/sup' },

        { label: 'Libérer un superviseur', href: '/assignments/sup/release' },

      ],

      teleconseillers: [

        { label: 'Liste des téléconseillers',  href: '/teleconseillers' },

        { label: 'Affecter un téléconseiller', href: '/assignments/tc' },

        { label: 'Réaffecter un téléconseiller',href: '/assignments/tc/reassign' },

      ],

      hierarchy: [

        { label: 'Organisation des équipes', href: '/assignments/hierarchy' },

        { label: 'Vue arborescente',          href: '/assignments/tree' },

      ],

      planning: [

        { label: 'Modèles de planning',      href: '/planning/models' },

        { label: 'Affectations des plannings', href: '/planning/assignments' },

        { label: 'Validation des plannings', href: '/planning/validate' },

        { label: 'Historique des plannings', href: '/planning/history' },

      ],

      timesheets: [

        { label: 'Saisie des heures superviseurs', href: '/timesheets' },

        { label: 'Validation des heures',          href: '/timesheets/validate' },

        { label: 'Historique des heures',          href: '/timesheets/history' },

        { label: 'Écarts planning/réel',           href: '/timesheets/gaps' },

      ],

      reports: [

        { label: 'Rapport équipe',       href: '/reports/team' },

        { label: 'Rapport productivité', href: '/reports/productivity' },

        { label: 'Rapport heures',       href: '/reports/timesheets' },

      ],

      account: [

        { label: 'Mon profil',            href: '/profile' },

        { label: 'Modifier mot de passe', href: '/profile/password' },

      ],

    },

  },


  // ─── SUPERVISEUR ─────────────────────────────────────────

  sup: {

    main: [

      { id: 'dashboard',  label: 'Dashboard',          icon: LayoutDashboard },

      { id: 'team',       label: 'Mon équipe',         icon: Users },

      { id: 'planning',   label: 'Planning',           icon: Calendar },

      { id: 'timesheets', label: 'Gestion des heures', icon: Clock },

      { id: 'reports',    label: 'Rapports',           icon: BarChart3 },

      { id: 'account',    label: 'Mon compte',         icon: User },

    ],

    sub: {

      dashboard: [

        { label: 'Tableau de bord', href: '/dashboard/sup' },

        { label: 'Notifications',   href: '/notifications' },

      ],

      team: [

        { label: 'Liste des téléconseillers',  href: '/teleconseillers' },

        { label: 'Détails des téléconseillers',href: '/teleconseillers/details' },

      ],

      planning: [

        { label: 'Mon planning',          href: '/planning/mine' },

        { label: 'Planning de l\'équipe', href: '/planning/team' },

      ],

      timesheets: [

        { label: 'Saisie des heures TC',  href: '/timesheets' },

        { label: 'Historique des heures', href: '/timesheets/history' },

        { label: 'Écarts planning/réel',  href: '/timesheets/gaps' },

      ],

      reports: [

        { label: 'Rapport équipe', href: '/reports/team' },

        { label: 'Rapport heures', href: '/reports/timesheets' },

      ],

      account: [

        { label: 'Mon profil',            href: '/profile' },

        { label: 'Modifier mot de passe', href: '/profile/password' },

      ],

    },

  },


  // ─── TÉLÉCONSEILLER ──────────────────────────────────────

  tc: {

    main: [

      { id: 'dashboard',  label: 'Dashboard',     icon: LayoutDashboard },

      { id: 'planning',   label: 'Mon planning',  icon: Calendar },

      { id: 'timesheets', label: 'Mes heures',    icon: Clock },

      { id: 'account',    label: 'Mon profil',    icon: User },

      { id: 'notifications', label: 'Notifications', icon: Bell },

    ],

    sub: {

      dashboard: [

        { label: 'Tableau de bord', href: '/dashboard/tc' },

      ],

      planning: [

        { label: 'Planning actuel',          href: '/planning/mine' },

        { label: 'Historique des plannings', href: '/planning/history' },

      ],

      timesheets: [

        { label: 'Heures validées',       href: '/timesheets' },

        { label: 'Historique des heures', href: '/timesheets/history' },

        { label: 'Heures supplémentaires',href: '/timesheets/overtime' },

      ],

      account: [

        { label: 'Informations personnelles',    href: '/profile' },

        { label: 'Modifier certaines informations', href: '/profile/edit' },

        { label: 'Modifier mot de passe',        href: '/profile/password' },

      ],

      notifications: [

        { label: 'Mes notifications', href: '/notifications' },

      ],

    },

  },

};


const currentMenu = computed(() => {
  const role = page.props.auth?.role;
  return menuConfig[role] ?? menuConfig.tc;
});

const currentSubMenu = computed(() => {
  return currentMenu.value.sub[activeMainMenu.value] ?? [];
});

const hasSubMenu = computed(() => currentSubMenu.value.length > 0);
const sidebarWidth = computed(() => (isHoveringSidebar.value || !hasSubMenu.value) ? 'w-64' : 'w-20');

const confirm = useConfirm();

const logout = () => {
  confirm.require({
    message: 'Êtes-vous sûr de vouloir vous déconnecter ?',
    header: 'Confirmation de déconnexion',
    icon: 'pi pi-exclamation-triangle',
    acceptProps: {
      label: 'Se déconnecter',
      severity: 'danger'
    },
    rejectProps: {
      label: 'Annuler',
      severity: 'secondary',
      variant: 'text'
    },
    accept: () => {
      router.post(route('logout'));
    }
  });
};
</script>

<template>
  <div class="flex h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 overflow-hidden font-sans">

    <aside
      :class="[
        'bg-white/70 backdrop-blur-xl border-r border-blue-100/50 text-slate-700 flex flex-col transition-all duration-300 shadow-lg z-30',
        sidebarWidth
      ]"
      @mouseenter="isHoveringSidebar = true"
      @mouseleave="isHoveringSidebar = false"
    >
      <div class="flex items-center px-4 border-b border-blue-100/50 bg-gradient-to-r from-blue-600 to-indigo-600 h-20 flex-shrink-0">
        <Link :href="route('dashboard')" class="flex items-center gap-3">
          <ApplicationLogo class="h-9 w-auto flex-shrink-0" />
          <span v-if="isHoveringSidebar || !hasSubMenu" class="text-xl font-black text-white tracking-tighter whitespace-nowrap uppercase">WipLite</span>
        </Link>
      </div>

      <nav class="flex-1 p-3 space-y-1 overflow-y-auto scrollbar-hide">
        <button
          v-for="item in currentMenu.main"
          :key="item.id"
          @click="activeMainMenu = item.id"
          :class="[
            'w-full flex items-center gap-3 px-3 py-3 rounded-xl transition-all duration-200 group',
            activeMainMenu === item.id
              ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/25'
              : 'text-slate-500 hover:bg-blue-50 hover:text-blue-700'
          ]"
        >
          <component :is="item.icon" :class="['w-5 h-5 flex-shrink-0 transition-transform group-hover:scale-110', activeMainMenu === item.id ? 'text-white' : 'text-slate-400 group-hover:text-blue-600']" />
          <span v-if="isHoveringSidebar || !hasSubMenu" class="font-bold text-sm whitespace-nowrap">{{ item.label }}</span>
        </button>
      </nav>
    </aside>

    <aside
      class="bg-white/95 backdrop-blur-md border-r border-blue-100/30 flex flex-col transition-all duration-500 overflow-hidden z-20 shadow-sm"
      :class="hasSubMenu ? 'w-64' : 'w-0 opacity-0'"
    >
      <div class="px-6 border-b border-blue-50 bg-gradient-to-b from-blue-50/50 to-transparent h-20 flex flex-col justify-center flex-shrink-0">
        <p class="text-[10px] font-black text-blue-400 uppercase tracking-[0.2em] mb-0.5">Navigation</p>
        <h3 class="font-black text-slate-800 text-lg leading-tight tracking-tight truncate">
          {{ currentMenu.main.find(m => m.id === activeMainMenu)?.label }}
        </h3>
      </div>

      <nav class="flex-1 p-4 space-y-1 overflow-y-auto scrollbar-hide">
        <Link
          v-for="item in currentSubMenu"
          :key="item.href"
          :href="item.href"
          class="flex items-center justify-between group px-4 py-3.5 rounded-2xl transition-all duration-200"
          :class="[
            (page.url === item.href || (item.href !== '/' && page.url.startsWith(item.href)))
              ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20'
              : 'text-slate-600 hover:bg-blue-50 hover:text-blue-600'
          ]"
        >
          <span class="text-[15px] font-bold tracking-tight">{{ item.label }}</span>
          <div :class="[
            'w-1.5 h-1.5 rounded-full transition-colors',
            (page.url === item.href || (item.href !== '/' && page.url.startsWith(item.href)))
              ? 'bg-white'
              : 'bg-blue-200 group-hover:bg-blue-600'
          ]"></div>
        </Link>
      </nav>
    </aside>

    <main class="flex-1 flex flex-col overflow-hidden">
      <header class="bg-white/80 backdrop-blur-md border-b border-blue-100/50 px-8 h-20 flex items-center justify-between shadow-sm z-10 flex-shrink-0">
        <div class="flex items-center gap-10">
          <slot name="header" />
        </div>

        <div class="flex items-center gap-6">
          <button class="p-2 text-slate-400 hover:bg-slate-50 rounded-full relative transition-colors">
            <Bell class="w-5 h-5" />
            <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
          </button>

          <div class="h-8 w-[1px] bg-slate-100"></div>

          <Dropdown align="right" width="56">
            <template #trigger>
              <button class="flex items-center gap-4 p-1 pr-4 rounded-full hover:bg-slate-50 transition-all group">
                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white font-black text-sm shadow-md group-hover:scale-105 transition-transform">
                  {{ page.props.auth?.user?.name?.charAt(0)?.toUpperCase() ?? 'U' }}
                </div>
                <div class="text-left hidden sm:block">
                  <p class="text-sm font-black text-slate-800 leading-none">{{ page.props.auth?.user?.name }}</p>
                  <p class="text-[10px] font-bold text-blue-500 uppercase tracking-tighter mt-1">{{ page.props.auth?.role }}</p>
                </div>
                <ChevronDown class="w-4 h-4 text-slate-400 group-hover:text-slate-600 transition-colors ml-2" />
              </button>
            </template>
            <template #content>
              <div class="px-4 py-3 border-b border-slate-50">
                 <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Mon compte</p>
              </div>
              <DropdownLink :href="route('profile.edit')" class="flex items-center gap-2 py-3 font-bold text-slate-600">
                <UserCircle class="w-4 h-4" /> Profil
              </DropdownLink>
              <div class="border-t border-slate-50"></div>
              <button @click="logout" class="w-full flex items-center gap-2 py-3 px-4 font-bold text-red-600 hover:bg-red-50 transition-colors">
                <LogOut class="w-4 h-4" /> Déconnexion
              </button>
            </template>
          </Dropdown>
        </div>
      </header>

      <div class="flex-1 overflow-auto p-8 scrollbar-hide">
        <slot />
      </div>
    </main>
    <Toast />
    <ConfirmDialog />
  </div>
</template>

<style>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>

