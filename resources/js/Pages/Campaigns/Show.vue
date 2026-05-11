<script setup>
/**
 * =========================================================
 * IMPORTS
 * =========================================================
 */

/**
 * Vue
 */
import { ref, computed } from 'vue'

/**
 * Inertia
 */
import { router, Link } from '@inertiajs/vue3'

/**
 * Layout principal
 */
import AppLayout from '@/Layouts/AppLayout.vue'

/**
 * PrimeVue Components
 */
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import Avatar from 'primevue/avatar'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import Toast from 'primevue/toast'

/**
 * PrimeVue Toast
 */
import { useToast } from 'primevue/usetoast'

/**
 * =========================================================
 * PROPS
 * =========================================================
 *
 * Données envoyées depuis Laravel
 */
const props = defineProps({

    /**
     * Campagne actuelle
     */
    campaign: Object,

    /**
     * Affectations actives
     */
    assignments: Array,

    /**
     * Historique
     */
    history: Array,

    /**
     * Employés disponibles
     */
    availableEmployees: Array,
})

/**
 * =========================================================
 * TOAST
 * =========================================================
 */
const toast = useToast()

/**
 * =========================================================
 * TAB ACTIVE
 * =========================================================
 */
const activeTab = ref(0)

/**
 * =========================================================
 * SURVOL D'UNE CARD
 * =========================================================
 *
 * Permet d'afficher les boutons
 * seulement au hover
 */
const hoveredId = ref(null)

/**
 * =========================================================
 * DIALOG LIBÉRATION
 * =========================================================
 */
const releaseDialog = ref(false)

/**
 * Affectation sélectionnée
 */
const selectedAssignment = ref(null)

/**
 * Données du formulaire de libération
 */
const releaseData = ref({

    /**
     * solo
     * cascade
     */
    mode: 'solo',

    /**
     * remplaçant
     */
    new_manager_id: null,

    /**
     * motif
     */
    reason: '',
})

/**
 * =========================================================
 * DIALOG RÉAFFECTATION
 * =========================================================
 */
const reassignDialog = ref(false)

/**
 * Données réaffectation
 */
const reassignData = ref({

    /**
     * nouveau manager
     */
    new_manager_id: null,

    /**
     * date début
     */
    start_date: new Date().toISOString().substring(0, 10),

    /**
     * raison
     */
    reason: '',
})

/**
 * =========================================================
 * CONSTRUCTION HIÉRARCHIQUE
 * =========================================================
 *
 * On transforme les affectations
 * en arbre :
 *
 * CP
 *   -> SUP
 *        -> TC
 */
const hierarchicalView = computed(() => {

    /**
     * Récupération des CP
     */
    const cps = props.assignments.filter(
        a => a.position.code === 'CP'
    )

    /**
     * Construction hiérarchique
     */
    return cps.map(cp => {

        /**
         * SUP du CP
         */
        const supervisors = props.assignments.filter(
            a =>
                a.position.code === 'SUP'
                &&
                a.manager_id === cp.employee_id
        )

        return {

            ...cp,

            /**
             * Sous employés
             */
            subordinates: supervisors.map(sup => {

                /**
                 * TC du superviseur
                 */
                const teleconseillers = props.assignments.filter(
                    a =>
                        a.position.code === 'TC'
                        &&
                        a.manager_id === sup.employee_id
                )

                return {

                    ...sup,

                    subordinates: teleconseillers
                }
            })
        }
    })
})

/**
 * =========================================================
 * OUVRIR LIBÉRATION
 * =========================================================
 */
const openRelease = (assignment) => {

    /**
     * Affectation sélectionnée
     */
    selectedAssignment.value = assignment

    /**
     * Reset formulaire
     */
    releaseData.value = {

        mode: 'solo',

        new_manager_id: null,

        reason: '',
    }

    /**
     * Ouvrir dialog
     */
    releaseDialog.value = true
}

/**
 * =========================================================
 * EXÉCUTER LIBÉRATION
 * =========================================================
 */
const executeRelease = () => {

    /**
     * Validation remplacement obligatoire
     * pour CP et SUP
     */
    if (
        selectedAssignment.value.position.code !== 'TC'
        &&
        releaseData.value.mode === 'solo'
        &&
        !releaseData.value.new_manager_id
    ) {

        toast.add({

            severity: 'error',

            summary: 'Erreur',

            detail: 'Veuillez sélectionner un remplaçant',

            life: 3000
        })

        return
    }

    /**
     * Appel backend
     */
    router.post(

        route(
            'assignments.release',
            selectedAssignment.value.id
        ),

        releaseData.value,

        {
            onSuccess: () => {

                toast.add({

                    severity: 'success',

                    summary: 'Succès',

                    detail: 'Ressource libérée',

                    life: 3000
                })

                releaseDialog.value = false
            }
        }
    )
}

/**
 * =========================================================
 * OUVRIR RÉAFFECTATION
 * =========================================================
 */
const openReassign = (assignment) => {

    /**
     * Affectation sélectionnée
     */
    selectedAssignment.value = assignment

    /**
     * Pré-remplissage
     */
    reassignData.value = {

        new_manager_id: assignment.manager_id,

        start_date: new Date().toISOString().substring(0, 10),

        reason: '',
    }

    /**
     * Ouvrir modal
     */
    reassignDialog.value = true
}

/**
 * =========================================================
 * EXÉCUTER RÉAFFECTATION
 * =========================================================
 */
const executeReassign = () => {

    /**
     * Validation
     */
    if (!reassignData.value.new_manager_id) {

        toast.add({

            severity: 'error',

            summary: 'Erreur',

            detail: 'Sélectionne un manager',

            life: 3000
        })

        return
    }

    /**
     * Backend
     */
    router.post(

        route(
            'assignments.reassign',
            selectedAssignment.value.id
        ),

        reassignData.value,

        {
            onSuccess: () => {

                toast.add({

                    severity: 'success',

                    summary: 'Succès',

                    detail: 'Ressource réaffectée',

                    life: 3000
                })

                reassignDialog.value = false
            }
        }
    )
}

/**
 * =========================================================
 * EMPLOYÉS REMPLAÇANTS
 * =========================================================
 */
const qualifiedReplacements = computed(() => {

    /**
     * Aucun employé sélectionné
     */
    if (!selectedAssignment.value) {
        return []
    }

    /**
     * Même position
     */
    return props.availableEmployees.filter(

        emp =>

            emp.position_id ===
            selectedAssignment.value.position_id

            &&

            emp.id !==
            selectedAssignment.value.employee_id
    )
})

/**
 * TCs disponibles pour affectation
 */
const availableTCs = computed(() => {
    return props.availableEmployees.filter(emp => emp.position?.code === 'TC')
})

/**
 * =========================================================
 * UTILITAIRES
 * =========================================================
 */

/**
 * Initiales avatar
 */
const getInitials = (first, last) => {

    return (
        (first?.[0] || '')
        +
        (last?.[0] || '')
    )
}

/**
 * Couleur status
 */
const getStatusSeverity = (status) => {

    return status === 'active'
        ? 'success'
        : 'warn'
}
</script>

<template>

<AppLayout>

<Toast />

<div class="p-6 bg-slate-50 min-h-screen">

    <!-- ================================================= -->
    <!-- HEADER -->
    <!-- ================================================= -->

    <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-100 mb-8">

        <div class="flex justify-between items-start">

            <div>

                <div class="flex items-center gap-3 mb-2">

                    <h1 class="text-3xl font-bold text-slate-900">
                        {{ campaign.name }}
                    </h1>

                    <Tag
                        :value="campaign.status"
                        :severity="getStatusSeverity(campaign.status)"
                    />
                </div>

                <p class="text-slate-500">
                    {{ campaign.description }}
                </p>
            </div>

            <!-- ACTIONS -->

            <div class="flex gap-3">

                <!-- PAGE AFFECTATION -->

                <Link
    v-if="campaign.status === 'active'"
    :href="route('assign.cp')"
>
                    <Button
                        label="Affecter"
                        icon="pi pi-user-plus"
                        class="rounded-xl"
                    />
                </Link>

            </div>

        </div>

    </div>

    <!-- ================================================= -->
    <!-- TABS -->
    <!-- ================================================= -->

    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">

        <Tabs v-model:value="activeTab">

            <!-- ========================================= -->
            <!-- TAB HEADER -->
            <!-- ========================================= -->

            <TabList class="px-6 pt-4">

                <Tab :value="0">
                    Affectations
                </Tab>

                <Tab :value="1">
                    Historique
                </Tab>

            </TabList>

            <TabPanels class="p-8">

                <!-- ===================================== -->
                <!-- HIÉRARCHIE -->
                <!-- ===================================== -->

                <TabPanel :value="0">

                    <div class="flex flex-col gap-6">

                        <!-- ============================= -->
                        <!-- TÉLÉCONSEILLERS DISPONIBLES (SI CAMPAGNE VIDE) -->
                        <!-- ============================= -->
                        <div v-if="hierarchicalView.length === 0" class="bg-blue-50 border border-blue-100 rounded-3xl p-8 mb-4">
                            <div class="flex items-center gap-4 mb-6">
                                <div class="bg-blue-600 p-3 rounded-2xl">
                                    <i class="pi pi-info-circle text-white text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-slate-900">Nouvelle Campagne</h3>
                                    <p class="text-slate-600">Cette campagne n'a pas encore de ressources affectées. Commencez par affecter un Chef de Plateau.</p>
                                </div>
                            </div>

                            <div v-if="availableTCs.length > 0">
                                <h4 class="font-semibold text-slate-700 mb-4 flex items-center gap-2">
                                    <i class="pi pi-users"></i>
                                    Téléconseillers disponibles ({{ availableTCs.length }})
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div v-for="tc in availableTCs.slice(0, 6)" :key="tc.id" class="bg-white p-4 rounded-2xl border border-slate-100 flex items-center gap-3">
                                        <Avatar :label="getInitials(tc.first_name, tc.last_name)" shape="circle" />
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium text-slate-900 truncate">{{ tc.first_name }} {{ tc.last_name }}</p>
                                            <p class="text-xs text-slate-500">{{ tc.email }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="availableTCs.length > 6" class="mt-4 text-center">
                                    <p class="text-sm text-slate-500 italic">Et {{ availableTCs.length - 6 }} autres téléconseillers disponibles...</p>
                                </div>
                            </div>
                            
                            <div class="mt-8 flex justify-center">
                                <Link :href="route('assign.cp')">
                                    <Button label="Commencer les affectations" icon="pi pi-arrow-right" iconPos="right" class="rounded-xl px-6" />
                                </Link>
                            </div>
                        </div>

                        <!-- ============================= -->
                        <!-- CP -->
                        <!-- ============================= -->

                        <div
                            v-for="cp in hierarchicalView"
                            :key="cp.id"
                        >

                            <!-- CARD CP -->

                            <div
                                class="bg-slate-50 border border-slate-100 rounded-2xl p-5 flex justify-between items-center"
                                @mouseenter="hoveredId = cp.id"
                                @mouseleave="hoveredId = null"
                            >

                                <div class="flex items-center gap-4">

                                    <Avatar
                                        :label="getInitials(
                                            cp.employee.first_name,
                                            cp.employee.last_name
                                        )"
                                        shape="circle"
                                        size="large"
                                    />

                                    <div>

                                        <div class="flex items-center gap-2">

                                            <h2 class="font-bold text-lg">
                                                {{ cp.employee.first_name }}
                                                {{ cp.employee.last_name }}
                                            </h2>

                                            <Tag value="CP" />
                                        </div>

                                        <p class="text-sm text-slate-500">
                                            {{ cp.employee.email }}
                                        </p>
                                    </div>

                                </div>

                                <!-- ACTIONS -->

                                <div
                                    v-show="hoveredId === cp.id"
                                    class="flex gap-2"
                                >

                                    <Button
                                        icon="pi pi-sign-out"
                                        severity="danger"
                                        text
                                        rounded
                                        @click="openRelease(cp)"
                                    />

                                </div>

                            </div>

                            <!-- ========================= -->
                            <!-- SUP -->
                            <!-- ========================= -->

                            <div
                                class="ml-12 mt-4 flex flex-col gap-4 border-l-2 border-slate-100 pl-8"
                            >

                                <div
                                    v-for="sup in cp.subordinates"
                                    :key="sup.id"
                                >

                                    <div
                                        class="bg-white border border-slate-100 rounded-2xl p-4 flex justify-between items-center"
                                        @mouseenter="hoveredId = sup.id"
                                        @mouseleave="hoveredId = null"
                                    >

                                        <div class="flex items-center gap-4">

                                            <Avatar
                                                :label="getInitials(
                                                    sup.employee.first_name,
                                                    sup.employee.last_name
                                                )"
                                                shape="circle"
                                            />

                                            <div>

                                                <div class="flex items-center gap-2">

                                                    <h3 class="font-semibold">
                                                        {{ sup.employee.first_name }}
                                                        {{ sup.employee.last_name }}
                                                    </h3>

                                                    <Tag value="SUP" severity="info" />
                                                </div>

                                            </div>

                                        </div>

                                        <!-- ACTIONS -->

                                        <div
                                            v-show="hoveredId === sup.id"
                                            class="flex gap-2"
                                        >

                                            <Button
                                                icon="pi pi-sync"
                                                severity="info"
                                                text
                                                rounded
                                                @click="openReassign(sup)"
                                            />

                                            <Button
                                                icon="pi pi-sign-out"
                                                severity="danger"
                                                text
                                                rounded
                                                @click="openRelease(sup)"
                                            />

                                        </div>

                                    </div>

                                    <!-- ================= -->
                                    <!-- TC -->
                                    <!-- ================= -->

                                    <div
                                        class="ml-10 mt-3 flex flex-col gap-3 border-l border-slate-100 pl-6"
                                    >

                                        <div
                                            v-for="tc in sup.subordinates"
                                            :key="tc.id"
                                        >

                                            <div
                                                class="bg-white border border-slate-100 rounded-xl p-3 flex justify-between items-center"
                                                @mouseenter="hoveredId = tc.id"
                                                @mouseleave="hoveredId = null"
                                            >

                                                <div class="flex items-center gap-3">

                                                    <Avatar
                                                        :label="getInitials(
                                                            tc.employee.first_name,
                                                            tc.employee.last_name
                                                        )"
                                                        shape="circle"
                                                    />

                                                    <div>

                                                        <div class="flex items-center gap-2">

                                                            <span class="font-medium">
                                                                {{ tc.employee.first_name }}
                                                                {{ tc.employee.last_name }}
                                                            </span>

                                                            <Tag
                                                                value="TC"
                                                                severity="success"
                                                            />

                                                        </div>

                                                    </div>

                                                </div>

                                                <!-- ACTIONS -->

                                                <div
                                                    v-show="hoveredId === tc.id"
                                                    class="flex gap-2"
                                                >

                                                    <Button
                                                        icon="pi pi-sync"
                                                        severity="info"
                                                        text
                                                        rounded
                                                        @click="openReassign(tc)"
                                                    />

                                                    <Button
                                                        icon="pi pi-sign-out"
                                                        severity="danger"
                                                        text
                                                        rounded
                                                        @click="openRelease(tc)"
                                                    />

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </TabPanel>

                <!-- ===================================== -->
                <!-- HISTORIQUE -->
                <!-- ===================================== -->

                <TabPanel :value="1">

                    <div class="flex flex-col gap-4">

                        <div
                            v-for="h in history"
                            :key="h.id"
                            class="border border-slate-100 rounded-2xl p-4"
                        >

                            <div class="flex items-center gap-3">

                                <Tag :value="h.action_type" />

                                <strong>
                                    {{ h.employee.first_name }}
                                    {{ h.employee.last_name }}
                                </strong>

                            </div>

                            <p class="text-sm text-slate-500 mt-2">
                                {{ h.reason }}
                            </p>

                        </div>

                    </div>

                </TabPanel>

            </TabPanels>

        </Tabs>

    </div>

    <!-- ================================================= -->
    <!-- DIALOG LIBÉRATION -->
    <!-- ================================================= -->

    <Dialog
        v-model:visible="releaseDialog"
        modal
        header="Libération"
        :style="{ width: '500px' }"
    >

        <div
            v-if="selectedAssignment"
            class="flex flex-col gap-5"
        >

            <div class="bg-slate-50 rounded-xl p-4">

                <p class="text-sm text-slate-600">

                    Libération de :

                    <strong>
                        {{ selectedAssignment.employee.first_name }}
                        {{ selectedAssignment.employee.last_name }}
                    </strong>

                </p>

            </div>

            <!-- CHOIX -->

            <div
                v-if="selectedAssignment.position.code !== 'TC'"
            >

                <label class="font-semibold block mb-3">

                    Mode de libération

                </label>

                <div class="flex flex-col gap-3">

                    <div
                        class="border rounded-xl p-4 cursor-pointer"
                        :class="releaseData.mode === 'solo'
                            ? 'border-blue-500 bg-blue-50'
                            : 'border-slate-200'"
                        @click="releaseData.mode = 'solo'"
                    >

                        <strong>Remplacer</strong>

                        <p class="text-sm text-slate-500">
                            Choisir un remplaçant
                        </p>

                    </div>

                    <div
                        class="border rounded-xl p-4 cursor-pointer"
                        :class="releaseData.mode === 'cascade'
                            ? 'border-red-500 bg-red-50'
                            : 'border-slate-200'"
                        @click="releaseData.mode = 'cascade'"
                    >

                        <strong>
                            Libérer toute l’équipe
                        </strong>

                    </div>

                </div>

            </div>

            <!-- REMPLAÇANT -->

            <div
                v-if="
                    releaseData.mode === 'solo'
                    &&
                    selectedAssignment.position.code !== 'TC'
                "
            >

                <label class="font-semibold block mb-2">
                    Remplaçant
                </label>

                <Select
                    v-model="releaseData.new_manager_id"
                    :options="qualifiedReplacements"
                    optionLabel="email"
                    optionValue="id"
                    filter
                    placeholder="Choisir..."
                    class="w-full"
                />

            </div>

            <!-- RAISON -->

            <div>

                <label class="font-semibold block mb-2">
                    Motif
                </label>

                <InputText
                    v-model="releaseData.reason"
                    class="w-full"
                />

            </div>

        </div>

        <template #footer>

            <div class="flex justify-end gap-3">

                <Button
                    label="Annuler"
                    text
                    @click="releaseDialog = false"
                />

                <Button
                    label="Confirmer"
                    severity="danger"
                    @click="executeRelease"
                />

            </div>

        </template>

    </Dialog>

</div>

</AppLayout>

</template>
