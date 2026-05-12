<script setup>
// Importation des composants PrimeVue nécessaires pour l'interface
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import Tag from 'primevue/tag';
import InputText from 'primevue/inputtext';
import { useToast } from 'primevue/usetoast';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

// Récupération des données envoyées par le contrôleur Laravel
const props = defineProps({
    assignments: Array, // Liste des affectations actives
    employees: Array,   // Liste des employés disponibles
    campaigns: Array,   // Liste des campagnes actives
    positions: Array    // Liste des postes (CP, SUP, TC)
});
console.log(props.employees);
// Variables d'état pour la gestion des modaux (dialogues)
const toast = useToast();
const assignmentDialog = ref(false); // Modal de nouvelle affectation
const releaseDialog = ref(false);    // Modal de libération/transfert
const submitted = ref(false);

const today = new Date().toISOString().split('T')[0];

// Objet pour stocker les données du formulaire de nouvelle affectation
const newAssignment = ref({
    employee_id: null,
    campaign_id: null,
    manager_id: null,
    position_id: null,
    start_date: new Date().toISOString().substr(0, 10) // Date du jour par défaut
});

// Objet pour stocker les données de libération d'une ressource
const selectedAssignment = ref(null);
const releaseData = ref({
    mode: 'solo', // Valeurs possibles : 'solo', 'cascade', 'transfer'
    new_manager_id: null,
    reason: ''
});

// --- LOGIQUE DE FILTRAGE ET RECHERCHE ---

// Liste des managers potentiels (Employés qui ont un poste de CP ou SUP)
const potentialManagers = computed(() => {
    return props.employees.filter(emp => {
        // On cherche le code du poste de l'employé pour savoir s'il peut être manager
        const pos = props.positions.find(p => p.id === emp.position_id);
        return pos && (pos.code === 'CP' || pos.code === 'SUP');
    });
});

// --- ACTIONS ---

/**
 * Ouvre le modal pour créer une nouvelle affectation
 */
const openNew = () => {
    newAssignment.value = {
        employee_id: null,
        campaign_id: null,
        manager_id: null,
        position_id: null,
        start_date: new Date().toISOString().substr(0, 10)
    };
    submitted.value = false;
    assignmentDialog.value = true;
};

/**
 * Enregistre une nouvelle affectation via Inertia
 */
const saveAssignment = () => {
    submitted.value = true;
    
    // Vérification basique des champs obligatoires
    if (!newAssignment.value.employee_id || !newAssignment.value.campaign_id || !newAssignment.value.position_id) {
        return;
    }

    router.post('/assignments', newAssignment.value, {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Affectation réussie', life: 3000 });
            assignmentDialog.value = false;
        }
    });
};

/**
 * Ouvre le modal de libération pour une affectation spécifique
 */
const confirmRelease = (data) => {
    selectedAssignment.value = data;
    releaseData.value = { mode: 'solo', new_manager_id: null, reason: '' };
    releaseDialog.value = true;
};

/**
 * Exécute la libération (solo, cascade ou transfert) via Inertia
 */
const executeRelease = () => {
    if (releaseData.value.mode === 'transfer' && !releaseData.value.new_manager_id) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Veuillez choisir un nouveau manager pour le transfert', life: 3000 });
        return;
    }

    router.post(`/assignments/${selectedAssignment.value.id}/release`, releaseData.value, {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Action effectuée avec succès', life: 3000 });
            releaseDialog.value = false;
        }
    });
};

/**
 * Détermine la couleur du tag en fonction du poste
 */
const getPositionSeverity = (code) => {
    switch (code) {
        case 'CP': return 'info';
        case 'SUP': return 'warn';
        case 'TC': return 'success';
        default: return 'secondary';
    }
};

</script>

<template>
    <AppLayout>
        <div class="p-4">
            <div class="card bg-white border-round shadow-1">
                <!-- Barre d'outils avec bouton d'ajout -->
                <div class="flex justify-between items-center p-4 border-b">
                    <h2 class="text-xl font-bold m-0">Gestion des Affectations</h2>
                    <Button label="Nouvelle Affectation" icon="pi pi-plus" class="p-button-primary" @click="openNew" />
                </div>

                <!-- Tableau des affectations actives -->
                <DataTable :value="props.assignments" paginator :rows="10" class="p-datatable-sm">
                    <template #header>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Liste des ressources affectées aux campagnes</span>
                        </div>
                    </template>

                    <Column header="Employé">
                        <template #body="slotProps">
                            <div class="font-bold">{{ slotProps.data.employee.first_name }} {{ slotProps.data.employee.last_name }}</div>
                            <small class="text-gray-500">{{ slotProps.data.employee.matricule }}</small>
                        </template>
                    </Column>

                    <Column header="Poste">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.position.name" :severity="getPositionSeverity(slotProps.data.position.code)" />
                        </template>
                    </Column>

                    <Column field="campaign.name" header="Campagne" sortable />

                    <Column header="Manager Direct">
                        <template #body="slotProps">
                            <span v-if="slotProps.data.manager">
                                {{ slotProps.data.manager.first_name }} {{ slotProps.data.manager.last_name }}
                            </span>
                            <span v-else class="text-gray-400 italic">Aucun</span>
                        </template>
                    </Column>

                    <Column field="start_date" header="Depuis le" sortable />

                    <Column header="Actions" bodyStyle="text-align: center">
                        <template #body="slotProps">
                            <Button icon="pi pi-sign-out" severity="danger" text rounded v-tooltip.top="'Libérer / Transférer'" @click="confirmRelease(slotProps.data)" />
                        </template>
                    </Column>
                </DataTable>
            </div>

            <!-- MODAL DE NOUVELLE AFFECTATION -->
            <Dialog v-model:visible="assignmentDialog" header="Nouvelle Affectation" :style="{ width: '500px' }" modal class="p-fluid">
                <div class="flex flex-col gap-4 mt-2">
                    <!-- Choix de l'employé -->
                    <div>
                        <label class="font-bold block mb-1">Employé</label>
                        <Dropdown v-model="newAssignment.employee_id" :options="props.employees" optionLabel="email" optionValue="id" filter placeholder="Choisir un employé" />
                    </div>

                    <!-- Choix de la campagne -->
                    <div>
                        <label class="font-bold block mb-1">Campagne</label>
                        <Dropdown v-model="newAssignment.campaign_id" :options="props.campaigns" optionLabel="name" optionValue="id" placeholder="Choisir une campagne" />
                    </div>

                    <!-- Choix du poste dans la campagne -->
                    <div>
                        <label class="font-bold block mb-1">Poste occupé</label>
                        <Dropdown v-model="newAssignment.position_id" :options="props.positions" optionLabel="name" optionValue="id" placeholder="Définir le rôle" />
                    </div>

                    <!-- Choix du manager (facultatif) -->
                    <div>
                        <label class="font-bold block mb-1">Manager Direct (Optionnel)</label>
                        <Dropdown v-model="newAssignment.manager_id" :options="potentialManagers" optionLabel="email" optionValue="id" filter showClear placeholder="Aucun manager" />
                    </div>

                    <!-- Date de début -->
                    <div>
                        <label class="font-bold block mb-1">Date de début</label>
                        <InputText type="date" v-model="newAssignment.start_date" :min="today" />
                    </div>
                </div>

                <template #footer>
                    <Button label="Annuler" severity="secondary" text @click="assignmentDialog = false" />
                    <Button label="Confirmer l'affectation" icon="pi pi-check" @click="saveAssignment" />
                </template>
            </Dialog>

            <!-- MODAL DE LIBÉRATION / TRANSFERT (Le cœur de la logique demandée) -->
            <Dialog v-model:visible="releaseDialog" header="Libérer une ressource" :style="{ width: '450px' }" modal class="p-fluid">
                <div v-if="selectedAssignment" class="flex flex-col gap-4 mt-2">
                    <div class="bg-blue-50 p-3 rounded text-blue-800 text-sm">
                        Vous allez libérer <b>{{ selectedAssignment.employee.first_name }} {{ selectedAssignment.employee.last_name }}</b> de la campagne <b>{{ selectedAssignment.campaign.name }}</b>.
                    </div>

                    <!-- Options de libération -->
                    <div>
                        <label class="font-bold block mb-2">Comment gérer ses subordonnés ?</label>
                        
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center gap-2 border p-2 rounded cursor-pointer hover:bg-gray-50" @click="releaseData.mode = 'solo'" :class="{'border-blue-500 bg-blue-50': releaseData.mode === 'solo'}">
                                <i class="pi pi-user"></i>
                                <div>
                                    <div class="font-bold">Libération seule</div>
                                    <div class="text-xs text-gray-500">Ses subordonnés restent sur la campagne sans manager direct.</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 border p-2 rounded cursor-pointer hover:bg-gray-50" @click="releaseData.mode = 'cascade'" :class="{'border-red-500 bg-red-50': releaseData.mode === 'cascade'}">
                                <i class="pi pi-users text-red-600"></i>
                                <div>
                                    <div class="font-bold text-red-600">Libération en cascade</div>
                                    <div class="text-xs text-gray-500">Toute sa chaîne (SUP et TC) est également libérée de la campagne.</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 border p-2 rounded cursor-pointer hover:bg-gray-50" @click="releaseData.mode = 'transfer'" :class="{'border-green-500 bg-green-50': releaseData.mode === 'transfer'}">
                                <i class="pi pi-sync text-green-600"></i>
                                <div>
                                    <div class="font-bold text-green-600">Transfert de chaîne</div>
                                    <div class="text-xs text-gray-500">Toute sa chaîne est rattachée à un autre responsable.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Choix du nouveau manager si transfert -->
                    <div v-if="releaseData.mode === 'transfer'">
                        <label class="font-bold block mb-1 text-green-700">Choisir le nouveau responsable</label>
                        <Dropdown v-model="releaseData.new_manager_id" :options="potentialManagers.filter(m => m.id !== selectedAssignment.employee_id)" optionLabel="email" optionValue="id" filter placeholder="Sélectionner le remplaçant" />
                    </div>

                    <!-- Raison du départ -->
                    <div>
                        <label class="font-bold block mb-1">Motif / Commentaire</label>
                        <InputText v-model="releaseData.reason" placeholder="Ex: Fin de contrat, mutation..." />
                    </div>
                </div>

                <template #footer>
                    <Button label="Annuler" severity="secondary" text @click="releaseDialog = false" />
                    <Button label="Valider l'action" icon="pi pi-check" :severity="releaseData.mode === 'cascade' ? 'danger' : 'primary'" @click="executeRelease" />
                </template>
            </Dialog>
        </div>
    </AppLayout>
</template>
