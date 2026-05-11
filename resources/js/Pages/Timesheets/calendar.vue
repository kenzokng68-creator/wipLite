<script setup>
import { computed, ref } from "vue";
import { Head, usePage, router } from "@inertiajs/vue3";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Dialog from "primevue/dialog";
import Button from 'primevue/button';
import { useToast } from "primevue/usetoast";
import Toast from "primevue/toast";
import TimesCard from "./TimesCard.vue";
import ConfirmDialog from "./ConfirmDialog.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { FilterMatchMode } from '@primevue/core/api';

const toast = useToast();
const props = defineProps({ calendar: Array });

// --- ÉTATS RÉACTIFS ---
 // Stocke les employés cochés dans le tableau
const selectedEmployees = ref([]);
      // Nombre de lignes affichées par page
const rows = ref(5);
   // Contrôle l'ouverture du formulaire de saisie
const displayModal = ref(false)
 // Contrôle l'ouverture du dialogue de clôture
const showConfirmDialog = ref(false);
// Données envoyées au composant TimesCard
const selectedData = ref(null);    
// Données envoyées au composant ConfirmDialog
const selectedForSubmit = ref(null); 

// --- GESTION DES UTILISATEURS ---
const user = computed(() => usePage().props.auth.user);
// Normalise le rôle en minuscules (ex: 'ADMIN' -> 'admin') pour faciliter les comparaisons
const role = computed(() => user.value?.role?.name?.toLowerCase() || 'tc');

/**
 * GÉNÉRATION DE LA PÉRIODE HEBDOMADAIRE
 * Calcule dynamiquement chaque jour entre la date de début et de fin du calendrier.
 */
const periodDates = computed(() => {
    if (!props.calendar?.length) return [];
    const start = new Date(props.calendar[0].period_start);
    const end = new Date(props.calendar[0].period_end);
    const dates = [];
    let current = new Date(start);

    while (current <= end) {
        // Ajoute la date formatée YYYY-MM-DD au tableau
        dates.push(new Date(current).toISOString().split("T")[0]);
        // Passe au jour suivant
        current.setDate(current.getDate() + 1);
    }
    return dates;
});

/**
 * FORMATAGE DES EN-TÊTES
 * Transforme une date technique en format lisible (ex: "lun. 20").
 */
const formatHeader = (d) => new Intl.DateTimeFormat("fr-FR", { weekday: "short", day: "numeric" }).format(new Date(d));

/**
 * RÉCUPÉRATION D'UNE ENTRÉE DE POINTAGE
 * Vérifie si un employé possède des heures enregistrées pour une date précise.
 */
const getEntry = (entries, date) => {
    if (!entries) return { is_empty: true };
    
    // Normalise la date recherchée (YYYY-MM-DD)
    const searchDate = date.split('T')[0];
    
    const found = entries.find(e => {
        if (!e.date) return false;
        // Normalise la date de l'entrée (YYYY-MM-DD)
        const entryDate = (typeof e.date === 'string') ? e.date.split('T')[0] : new Date(e.date).toISOString().split('T')[0];
        return entryDate === searchDate;
    });
    
    return found ? { ...found, is_empty: false } : { is_empty: true };
};

/**
 * LOGIQUE VISUELLE DES CELLULES
 * Définit la couleur de fond de la case en fonction du statut et du remplissage.
 */
const getCellClass = (timesheet, date) => {
    const entry = getEntry(timesheet.entries, date);
    
    // 1. Si aucune donnée : Rouge (Absent)
    if (entry.is_empty) return 'bg-red-50 border-red-200 text-red-700'; 
    // 2. Si heures saisies < heures prévues : Orange (Incomplet)
    if (parseFloat(entry.total_hours) < parseFloat(entry.planned_hours)) {
        return 'bg-orange-50 border-orange-200 text-orange-700';
    }
    // 3. Si la semaine est validée : Émeraude (Verrouillé)
    if (timesheet.status === 'soumis') return 'bg-emerald-100 border-emerald-300 text-emerald-800 font-bold';
    
    // 4. Par défaut : Vert (Conforme)
    return 'bg-green-50 border-green-200 text-green-800';
};

/**
 * OUVERTURE DU FORMULAIRE INDIVIDUEL
 * Prépare les données de l'employé et du jour sélectionné avant d'ouvrir le modal.
 */
const openTimeCard = (timesheet, date) => {
    const entry = getEntry(timesheet.entries, date);
    selectedData.value = {
        timesheet_id: timesheet.id,
        status: timesheet.status,
        role: role.value,
        employee_name: `${timesheet.employee.first_name} ${timesheet.employee.last_name}`,
        date: date,
        // On passe l'entrée existante si elle existe, sinon null
        entry: entry.is_empty ? null : entry
    };
    displayModal.value = true;
};

/**
 * OUVERTURE DU DIALOGUE DE CLÔTURE
 * Prépare l'ID de la feuille de temps pour la soumission finale.
 */
const openConfirm = (timesheet) => {
    selectedForSubmit.value = { 
        id: timesheet.id, 
        name: `${timesheet.employee.first_name} ${timesheet.employee.last_name}` 
    };
    showConfirmDialog.value = true;
};

/**
 * GESTION DE LA SAISIE GROUPÉE (BULK)
 * Vérifie l'éligibilité de la sélection avant d'ouvrir le mode multiple.
 */
const openBulkEdit = () => {
    if (selectedEmployees.value.length === 0) return;

    // Sécurité : Vérifie si un employé de la sélection est déjà verrouillé (soumis)
    const hasSubmitted = selectedEmployees.value.some(emp => emp.status === 'soumis');
    
    if (hasSubmitted) {
        toast.add({ 
            severity: 'error', 
            summary: 'Action impossible', 
            detail: 'Certains employés sélectionnés sont déjà en statut soumis.', 
            life: 5000 
        });
        return; 
    }

    // Préparation des données pour plusieurs IDs simultanés
    selectedData.value = {
        isBulk: true,
        timesheet_ids: selectedEmployees.value.map(item => item.id),
        employee_name: `${selectedEmployees.value.length} employés sélectionnés`,
        all_dates: periodDates.value,
        role: role.value,
        status: 'brouillon',
        timesheet_id: selectedEmployees.value[0].id // ID pivot pour le formulaire
    };
    displayModal.value = true;
};

/**
 * VALIDATION HORaire
 * Vérifie que les horaires sont cohérents (check_in < check_out)
 */
const validateTimeEntry = (checkIn, checkOut, breakDuration = 0) => {
    if (!checkIn || !checkOut) return { isValid: false, error: 'Heures de début et de fin requises' };
    
    // Convertit en minutes pour comparaison
    const [inHours, inMinutes] = checkIn.split(':').map(Number);
    const [outHours, outMinutes] = checkOut.split(':').map(Number);
    
    // Vérifie que les heures sont valides
    if (inHours > 23 || outHours > 23 || inMinutes > 59 || outMinutes > 59) {
        return { isValid: false, error: 'Format horaire invalide' };
    }
    
    const inTotalMinutes = inHours * 60 + inMinutes;
    const outTotalMinutes = outHours * 60 + outMinutes;
    
    // Vérifie que l'arrivée est avant le départ
    if (inTotalMinutes >= outTotalMinutes) {
        return { isValid: false, error: "L'heure d'arrivée doit être avant l'heure de départ" };
    }
    
    // Calcule la durée de travail
    let workMinutes = outTotalMinutes - inTotalMinutes;
    
    // Soustrait la pause
    if (breakDuration) {
        workMinutes -= parseInt(breakDuration);
    }
    
    // Vérifie que la durée de travail est positive
    if (workMinutes <= 0) {
        return { isValid: false, error: 'La durée de travail doit être positive' };
    }
    
    return { isValid: true, workMinutes, workHours: workMinutes / 60 };
};

/**
 * CALCUL DES TOTALS HEBDOMADAIRES
 * Addition STRICTE des heures réelles affichées dans les cases
 */
const getTotalsData = (timesheet) => {
    if (!timesheet?.entries?.length) return { worked: 0, planned: 0 };
    
    let totalWorked = 0;
    let totalPlanned = 0;
    
    // On utilise les dates de la période pour être sûr de ce qu'on calcule
    periodDates.value.forEach(dateStr => {
        const entry = getEntry(timesheet.entries, dateStr);
        if (!entry.is_empty) {
            // Additionner les heures réelles stockées
            totalWorked += parseFloat(entry.total_hours || 0);
            // Additionner les heures prévues stockées
            totalPlanned += parseFloat(entry.planned_hours || 0);
        }
    });
    
    return {
        worked: Math.round(totalWorked * 100) / 100,
        planned: Math.round(totalPlanned * 100) / 100
    };
};

/**
 * 4. RECHERCHE D'ENTRÉE
 * Pour chaque cellule, on cherche si l'employé a une donnée pour cette date
 */
const getEntryByDate = (timesheet, date) => {
    const entries = timesheet?.entries ?? [];
    return entries.find((e) => e.date === date) || null;
};

const openTimesCard = (timesheet, date) => {
    const entry = getEntryByDate(timesheet, date);

    selectedCell.value = {
        employee: timesheet.employee,
        timesheet_id: timesheet.id,
        status: timesheet.status,
        date,
        entry,
    };

    showTimesCard.value = true;
};

/**
 * 5. CALCUL DES TOTAUX
 * Calcule la somme des heures réelles et prévues pour une feuille de temps (une ligne)
 */
const calculateTotals = (timesheet) => {
    const entries = timesheet?.entries ?? [];
    let worked = 0;
    let planned = 0;

    for (const entry of entries) {
        worked += parseFloat(entry.total_hours || 0);
        planned += parseFloat(entry.planned_hours || 0);
    }

    return { worked: worked.toFixed(1), planned: planned.toFixed(1) };
};
</script>

<template>
    <!-- SEO et Notifications -->
    <Head title="Calendrier de Pointage" />
    <Toast />

    <AppLayout>
        <!-- Conteneur Principal : Fond gris perle très doux -->
        <div class="p-8 bg-[#f8fafc] min-h-screen">
            
            <!-- SECTION EN-TÊTE : Titre et Actions -->
            <div class="flex justify-between items-end mb-8">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 uppercase tracking-tighter leading-none">
                        Gestion des Flux
                    </h1>
                    <p class="text-slate-500 text-sm mt-2 font-medium italic">
                        Suivi hebdomadaire des présences et pointages
                    </p>
                </div>
                <div class="flex gap-3">
                    <Button v-if="selectedEmployees.length > 0" 
                            label="Saisie Groupée" 
                            icon="pi pi-users" 
                            class="p-button-raised p-button-help p-button-sm font-bold border-0 shadow-lg shadow-purple-200/50" 
                            @click="openBulkEdit" />
                </div>
            </div>

            <!-- TABLEAU : Blanc pur, ombres diffuses et coins arrondis -->
            <DataTable 
                :value="calendar" 
                v-model:selection="selectedEmployees" 
                :paginator="true" 
                :rows="rows" 
                dataKey="id" 
                scrollable 
                class="custom-datatable overflow-hidden rounded-3xl border-0 bg-white shadow-2xl shadow-slate-200/40"
            >
                <!-- Colonne de Sélection (Blanche) -->
                <Column selectionMode="multiple" 
                        headerStyle="background-color: #ffffff; width: 3rem;" 
                        class="!bg-white border-b border-slate-50" 
                        frozen 
                        :disabledSelection="data => data.status === 'soumis'">
                </Column>
                
                <!-- Colonne Employé (Forcée en blanc même si figée) -->
                <Column frozen 
                        header="Collaborateurs" 
                        class="border-b border-slate-50 !bg-white" 
                        headerStyle="background: #ffffff;"
                        style="min-width: 280px">
                    <template #body="{ data }">
                        <div class="flex flex-col py-3 px-2 bg-white">
                            <span class="font-bold text-slate-800 text-sm tracking-tight capitalize">
                                {{ data.employee.first_name }} {{ data.employee.last_name }}
                            </span>
                            <div class="flex items-center gap-2 mt-1.5">
                                <span class="px-2 py-0.5 bg-slate-100 text-slate-500 text-[9px] font-bold rounded-md uppercase border border-slate-200">
                                    MAT: {{ data.employee.matricule }}
                                </span>
                                <span class="text-[10px] text-slate-400 font-medium">
                                    Resp: {{ data.validator?.first_name || 'N/A' }}
                                </span>
                            </div>
                        </div>
                    </template>
                </Column>

                <!-- Colonnes jours (Cellules colorées harmonieuses) -->
                <Column v-for="date in periodDates" :key="date" :header="formatHeader(date)" class="text-center border-b border-slate-50 bg-white">
                    <template #body="{ data: timesheet }">
                        <div
                            :class="[
                                getCellClass(timesheet, date),
                                'm-1 p-2 rounded-xl border cursor-pointer transition-all hover:shadow-md min-h-[60px] flex flex-col items-center justify-center'
                            ]"
                            @click="openTimeCard(timesheet, date)"
                        >
                            <template v-if="!getEntry(timesheet.entries, date).is_empty">
                                <span class="text-xs font-black">
                                    {{ getEntry(timesheet.entries, date).total_hours }}h
                                </span>
                            </template>
                            <template v-else>
                                <span class="text-[10px] opacity-30 italic font-bold">--:--</span>
                            </template>
                        </div>
                    </template>
                </Column>

                <!-- NOUVELLE COLONNE : STATUT -->
                <Column header="Statut" class="text-center border-b border-slate-50 bg-white" style="min-width: 100px">
                    <template #body="{ data }">
                        <span :class="[
                            'px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider shadow-sm border',
                            data.status === 'soumis' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 
                            data.status === 'valide' ? 'bg-blue-50 text-blue-600 border-blue-100' : 
                            'bg-slate-50 text-slate-500 border-slate-200'
                        ]">
                            {{ data.status }}
                        </span>
                    </template>
                </Column>

                <Column header="Total" class="text-right font-bold">
                    <template #body="{ data }">
                        <div class="flex flex-col items-end px-2">
                            <span class="text-blue-700 text-sm font-black">
                                {{ getTotalsData(data).worked }}h
                            </span>
                            <span class="text-[9px] text-slate-400 font-bold uppercase">
                                Prévu: {{ getTotalsData(data).planned }}h
                            </span>
                        </div>
                    </template>
                </Column>

                <!-- Actions de validation -->
                <Column header="Validation" class="text-center border-b border-slate-50 bg-white" style="min-width: 90px">
                    <template #body="{ data }">
                        <button v-if="(role === 'cp' || role === 'admin') && data.status !== 'soumis'" 
                                @click="openConfirm(data)" 
                                class="p-2.5 text-blue-600 bg-blue-50 hover:bg-blue-600 hover:text-white rounded-xl transition-all shadow-sm border border-blue-100">
                            <i class="pi pi-send text-sm"></i>
                        </button>
                        
                        <div v-else-if="data.status === 'soumis'" 
                             class="w-10 h-10 bg-emerald-50 text-emerald-500 flex items-center justify-center rounded-full border border-emerald-100 mx-auto">
                            <i class="pi pi-check-circle text-lg"></i>
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- MODALS -->
        <Dialog v-model:visible="displayModal" header="Détails du Pointage" :modal="true" :style="{ width: '400px' }" class="white-modal">
            <TimesCard v-if="displayModal" :data="selectedData" @close="displayModal = false" />
        </Dialog>

        <ConfirmDialog v-model:visible="showConfirmDialog" :timesheetId="selectedForSubmit?.id" :employeeName="selectedForSubmit?.name" />
    </AppLayout>
</template>

<style scoped>
/* Nettoyage global du tableau PrimeVue */
.custom-datatable :deep(.p-datatable-wrapper) {
    background-color: #ffffff !important;
}

/* Force l'en-tête en blanc pur */
.custom-datatable :deep(.p-datatable-thead > tr > th) {
    background-color: #ffffff !important;
    color: #64748b !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 20px 15px !important;
    border-bottom: 1px solid #f1f5f9 !important;
}

/* Force les colonnes figées (Collaborateurs) en blanc */
.custom-datatable :deep(.p-datatable-tbody > tr > td.p-frozen-column),
.custom-datatable :deep(.p-datatable-thead > tr > th.p-frozen-column) {
    background-color: #ffffff !important;
    background: #ffffff !important;
}

/* Style de la pagination blanche */
.custom-datatable :deep(.p-paginator) {
    background-color: #ffffff !important;
    border-top: 1px solid #f1f5f9 !important;
    border-radius: 0 0 24px 24px !important;
    padding: 15px !important;
}

/* Harmonisation des lignes du tableau */
.custom-datatable :deep(.p-datatable-tbody > tr) {
    background-color: #ffffff !important;
    color: #1e293b !important;
}

/* Supprime les bordures et centrage */
:deep(.p-column-header-content) {
    justify-content: center !important;
}

/* Correction spécifique pour l'ombre de la colonne figée */
.custom-datatable :deep(.p-datatable-tbody > tr > td.p-frozen-column) {
    box-shadow: none !important;
    border-right: 1px solid #f1f5f9 !important;
}
</style>


