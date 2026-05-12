<script setup>
// Importation des composants PrimeVue
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import Menu from 'primevue/menu';
import SelectButton from 'primevue/selectbutton';
import ToggleSwitch from 'primevue/toggleswitch';
import { useToast } from 'primevue/usetoast';
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

// Props reçues du contrôleur Laravel
const props = defineProps({
    campaigns: Array, // Liste des campagnes avec assignment_count
});

const toast = useToast();
const dt = ref();

// États pour les modaux
const campaignDialog = ref(false);
const deleteDialog = ref(false);
const selectedCampaign = ref(null);
const campaign = ref({ status: 'inactive' });
const submitted = ref(false);

const today = new Date().toISOString().split('T')[0];

// Menu contextuel
const menu = ref();
const menuItems = ref([]);

// Filtres
const searchQuery = ref('');
const statusFilter = ref('Tous');
const statusOptions = ref(['Tous', 'Active', 'Inactive', 'Terminee']);
const showClosed = ref(false);

/**
 * Filtrage des campagnes selon la recherche, le statut et l'option d'affichage des clôturées
 */
const filteredCampaigns = computed(() => {
    return props.campaigns.filter(c => {
        const matchesSearch = c.name.toLowerCase().includes(searchQuery.value.toLowerCase());
        
        // Normalisation pour la comparaison (gestion des accents)
        const normalize = (str) => str.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        
        const matchesStatus = statusFilter.value === 'Tous' || normalize(c.status) === normalize(statusFilter.value);
        
        const isClosed = normalize(c.status) === 'terminee';
        const hideClosed = !showClosed.value && isClosed && normalize(statusFilter.value) !== 'terminee';
        
        return matchesSearch && matchesStatus && !hideClosed;
    });
});

/**
 * Gestion du menu contextuel par ligne
 */
const toggleMenu = (event, data) => {
    event.stopPropagation(); // Empêche le clic sur la ligne
    selectedCampaign.value = data;
    
    const items = [
        { label: 'Voir le détail', icon: 'pi pi-eye', command: () => router.get(`/campaigns/${data.id}`) },
        { label: 'Modifier', icon: 'pi pi-pencil', command: () => editCampaign(data) }
    ];

    // On n'ajoute le bouton de changement de statut que si la campagne n'est pas terminée
    if (data.status !== 'terminee') {
        const nextStatus = data.status === 'active' ? 'inactive' : 'active';
        const statusLabel = data.status === 'active' ? 'Désactiver' : 'Activer';
        items.push({ 
            label: statusLabel, 
            icon: data.status === 'active' ? 'pi pi-power-off' : 'pi pi-play', 
            command: () => toggleStatus(data, nextStatus) 
        });
    }

    // Bouton de clôture (si non terminée)
    items.push({ separator: true });
    if (data.status !== 'terminee') {
        items.push({ 
            label: 'Clôturer', 
            icon: 'pi pi-times-circle', 
            class: 'text-red-500', 
            command: () => confirmClose(data) 
        });
    }

    menuItems.value = items;
    menu.value.toggle(event);
};

/**
 * Basculer le statut d'une campagne
 */
const toggleStatus = (data, nextStatus) => {
    router.patch(`/campaigns/${data.id}/status`, { status: nextStatus }, {
        onSuccess: () => {
            toast.add({ 
                severity: 'success', 
                summary: 'Statut mis à jour', 
                detail: `La campagne est maintenant ${nextStatus}`, 
                life: 3000 
            });
        }
    });
};

/**
 * Navigation vers la page de détail au clic sur une ligne
 */
const onRowClick = (event) => {
    // Si on a cliqué sur un bouton ou un menu, on ne navigue pas
    if (event.originalEvent.target.closest('button') || event.originalEvent.target.closest('.p-menu')) {
        return;
    }
    router.get(`/campaigns/${event.data.id}`);
};

// Fonctions CRUD 
const openNew = () => {
    campaign.value = { status: 'inactive' };
    submitted.value = false;
    campaignDialog.value = true;
};

const editCampaign = (data) => {
    // Formatage des dates pour l'input date
    const formatted = { ...data };
    if (data.start_date) {
        const p = data.start_date.split('/');
        if (p.length === 3) formatted.start_date = `${p[2]}-${p[1]}-${p[0]}`;
    }
    if (data.end_date) {
        const p = data.end_date.split('/');
        if (p.length === 3) formatted.end_date = `${p[2]}-${p[1]}-${p[0]}`;
    }
    campaign.value = formatted;
    campaignDialog.value = true;
};

const saveCampaign = () => {
    submitted.value = true;
    if (!campaign.value.name) return;

    if (campaign.value.id) {
        router.put(`/campaigns/${campaign.value.id}`, campaign.value, {
            onSuccess: () => {
                toast.add({ severity: 'success', summary: 'Succès', detail: 'Campagne mise à jour', life: 3000 });
                campaignDialog.value = false;
            }
        });
    } else {
        router.post('/campaigns', campaign.value, {
            onSuccess: () => {
                toast.add({ severity: 'success', summary: 'Succès', detail: 'Campagne créée', life: 3000 });
                campaignDialog.value = false;
            }
        });
    }
};

const confirmClose = (data) => {
    selectedCampaign.value = data;
    deleteDialog.value = true;
};

const closeCampaign = () => {
    router.delete(`/campaigns/${selectedCampaign.value.id}`, {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Campagne clôturée avec succès', life: 3000 });
            deleteDialog.value = false;
        }
    });
};

const getStatusSeverity = (status) => {
    switch (status.toLowerCase()) {
        case 'active': return 'success';
        case 'inactive': return 'warn';
        case 'terminee': return 'secondary';
        default: return 'info';
    }
};
</script>

<template>
    <AppLayout>
        <div class="p-6 bg-slate-50 min-h-screen">
            <!-- HEADER (Image 3) -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900">Campagnes</h1>
                    <p class="text-slate-500">{{ props.campaigns.length }} campagnes au total</p>
                </div>
                <Button label="Nouvelle campagne" icon="pi pi-plus" class="!bg-blue-600 rounded-lg px-4 py-2" @click="openNew" />
            </div>

            <!-- FILTRES (Image 3) -->
            <div class="flex flex-wrap gap-4 items-center mb-6">
                <div class="p-input-icon-left flex-1 min-w-[300px]">
                    <InputText v-model="searchQuery" placeholder="Rechercher une campagne..." class="w-full pl-10 rounded-xl border-slate-200" />
                </div>
                <div class="flex items-center gap-3 bg-white px-4 py-2 rounded-xl border border-slate-100 shadow-sm">
                    <span class="text-sm font-medium text-slate-600">Afficher les clôturées</span>
                    <ToggleSwitch v-model="showClosed" />
                </div>
                <SelectButton v-model="statusFilter" :options="statusOptions" class="custom-select-button" />
            </div>

            <!-- TABLEAU (Image 3) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <DataTable :value="filteredCampaigns" class="p-datatable-custom cursor-pointer" responsiveLayout="stack" 
                    @row-click="onRowClick" rowHover >
                    <Column field="name" header="Nom" sortable>
                        <template #body="slotProps">
                            <span class="font-bold text-slate-900 hover:text-blue-600 transition-colors">
                                {{ slotProps.data.name }}
                            </span>
                        </template>
                    </Column>
                    
                    <Column field="description" header="Description">
                        <template #body="slotProps">
                            <span class="text-slate-500 truncate block max-w-xs">{{ slotProps.data.description }}</span>
                        </template>
                    </Column>

                    <Column field="start_date" header="Début" sortable />
                    <Column field="end_date" header="Fin" sortable />

                    <Column header="Statut">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.status" :severity="getStatusSeverity(slotProps.data.status)" rounded />
                        </template>
                    </Column>

                    <Column header="Affectations">
                        <template #body="slotProps">
                            <div class="flex items-center gap-2 text-slate-600">
                                <i class="pi pi-users text-sm" />
                                <span>{{ slotProps.data.assignments_count || 0 }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column header="Actions" class="w-20">
                        <template #body="slotProps">
                            <Button icon="pi pi-ellipsis-v" severity="secondary" text rounded @click="toggleMenu($event, slotProps.data)" />
                        </template>
                    </Column>
                </DataTable>
                <Menu ref="menu" :model="menuItems" :popup="true" />
            </div>

            <!-- MODAL CRÉATION/EDITION -->
            <Dialog v-model:visible="campaignDialog" :header="campaign.id ? 'Modifier la campagne' : 'Nouvelle campagne'" modal class="p-fluid rounded-2xl" :style="{width: '500px'}">
                <div class="flex flex-col gap-4 mt-2">
                    <div>
                        <label class="font-semibold block mb-1">Nom de la campagne *</label>
                        <InputText v-model="campaign.name" placeholder="Ex: Campagne Highfive" :class="{'p-invalid': submitted && !campaign.name}" />
                    </div>
                    <div>
                        <label class="font-semibold block mb-1">Description</label>
                        <Textarea v-model="campaign.description" rows="3" placeholder="Objectifs et détails..." />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="font-semibold block mb-1">Date début *</label>
                            <InputText type="date" v-model="campaign.start_date" :min="campaign.id ? undefined : today" />
                        </div>
                        <div>
                            <label class="font-semibold block mb-1">Date fin</label>
                            <InputText type="date" v-model="campaign.end_date" :min="campaign.start_date || today" />
                        </div>
                    </div>
                    <div>
                        <label class="font-semibold block mb-2">Statut</label>
                        <div class="flex gap-2">
                            <Button label="Active" :outlined="campaign.status !== 'active'" rounded @click="campaign.status = 'active'" severity="success" size="small" />
                            <Button label="Inactive" :outlined="campaign.status !== 'inactive'" rounded @click="campaign.status = 'inactive'" severity="warn" size="small" />
                            <Button label="Terminée" :outlined="campaign.status !== 'terminee'" rounded @click="campaign.status = 'terminee'" severity="secondary" size="small" />
                        </div>
                    </div>
                </div>
                <template #footer>
                    <Button label="Annuler" severity="secondary" text @click="campaignDialog = false" />
                    <Button label="Enregistrer" icon="pi pi-check" @click="saveCampaign" class="px-4" />
                </template>
            </Dialog>

            <!-- MODAL SUPPRESSION (CLÔTURE) -->
            <Dialog v-model:visible="deleteDialog" header="Confirmation de clôture" modal :style="{width: '400px'}">
                <div class="flex items-center gap-3">
                    <i class="pi pi-exclamation-triangle text-red-500 text-3xl" />
                    <div class="flex flex-col">
                        <span>Voulez-vous clôturer la campagne <b>{{ selectedCampaign?.name }}</b> ?</span>
                        <span class="text-sm text-slate-500 mt-2 italic">Cette action desaffectera toutes les ressources.".</span>
                    </div>
                </div>
                <template #footer>
                    <Button label="Annuler" severity="secondary" text @click="deleteDialog = false" />
                    <Button label="Confirmer la clôture" severity="danger" @click="closeCampaign" />
                </template>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-selectbutton) {
    background: #f1f5f9;
    padding: 4px;
    border-radius: 12px;
}
:deep(.p-selectbutton .p-button) {
    border: none;
    background: transparent;
    color: #64748b;
    border-radius: 8px;
    transition: all 0.2s;
    font-weight: 500;
}
:deep(.p-selectbutton .p-button.p-highlight) {
    background: white;
    color: #2563eb;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
:deep(.p-datatable-custom .p-datatable-thead > tr > th) {
    background: #f8fafc;
    color: #64748b;
    font-weight: 600;
    font-size: 0.875rem;
    padding: 1rem;
}
:deep(.p-datatable-custom .p-datatable-tbody > tr > td) {
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
}
</style>
