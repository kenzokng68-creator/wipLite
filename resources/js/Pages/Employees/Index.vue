<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { ref, watch } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";

import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Button from "primevue/button";
import Toolbar from "primevue/toolbar";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import InputText from "primevue/inputtext";
import Tag from "primevue/tag";
import Dialog from "primevue/dialog";
import Select from "primevue/select";
import DatePicker from "primevue/datepicker";
import InputNumber from "primevue/inputnumber";
import Message from "primevue/message";
import Toast from "primevue/toast";
import ConfirmDialog from "primevue/confirmdialog";

// ---------------------------------------------------------
// PROPS
// ---------------------------------------------------------
const props = defineProps({
    employees: Object,
    positions: Array,
    filters: Object,
});

// ---------------------------------------------------------
// INSTANCES
// ---------------------------------------------------------
const toast = useToast();
const confirm = useConfirm();
const dt = ref();

// ---------------------------------------------------------
// RECHERCHE — debounce automatique
// ---------------------------------------------------------
const search = ref(props.filters?.search ?? "");
let debounceTimer = null;

watch(search, (value) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(
            route("employees.index"),
            {
                search: value,
                page: 1,
            },
            { preserveState: true, replace: true },
        );
    }, 400);
});

// ---------------------------------------------------------
// DIALOG — état
// ---------------------------------------------------------
const dialogVisible = ref(false);
const isEditing = ref(false);
const submitted = ref(false);

// ---------------------------------------------------------
// FORMULAIRE
// ---------------------------------------------------------
const form = useForm({
    id: null,
    matricule: "",
    first_name: "",
    last_name: "",
    birth_date: null,
    phone: "",
    email: "",
    address: "",
    position_id: null,
    salary_base: null,
    status: "actif",
    user_id: null,
});

const statuses = [
    { label: "Actif", value: "actif" },
    { label: "Inactif", value: "inactif" },
    { label: "Suspendu", value: "suspendu" },
];

// ---------------------------------------------------------
// MÉTHODES
// ---------------------------------------------------------
const openCreate = () => {
    isEditing.value = false;
    submitted.value = false;
    form.reset();
    form.clearErrors();
    dialogVisible.value = true;
};

const openEdit = (employee) => {
    isEditing.value = true;
    submitted.value = false;
    form.clearErrors();

    form.id = employee.id;
    form.matricule = employee.matricule;
    form.first_name = employee.first_name;
    form.last_name = employee.last_name;
    form.birth_date = employee.birth_date ? new Date(employee.birth_date) : null;
    form.phone = employee.phone ?? "";
    form.email = employee.email;
    form.address = employee.address ?? "";
    form.position_id = employee.position_id;
    form.salary_base = parseFloat(employee.salary_base);
    form.status = employee.status;
    form.user_id = employee.user_id;

    dialogVisible.value = true;
};

const closeDialog = () => {
    dialogVisible.value = false;
    submitted.value = false;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    submitted.value = true;
    const data = {
        ...form.data(),
        birth_date: form.birth_date
            ? new Date(form.birth_date).toISOString().split("T")[0]
            : null,
    };

    if (isEditing.value) {
        form.transform(() => data).put(route("employees.update", form.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({ severity: "success", summary: "Mis à jour", detail: "Employé mis à jour.", life: 3000 });
                closeDialog();
            },
        });
    } else {
        form.transform(() => data).post(route("employees.store"), {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({ severity: "success", summary: "Créé", detail: "Employé créé avec succès.", life: 3000 });
                closeDialog();
            },
        });
    }
};

const getStatusSeverity = (status) => {
    switch (status) {
        case "actif": return "success";
        case "suspendu": return "warn";
        case "inactif": return "danger";
        default: return null;
    }
};

const exportCSV = () => dt.value.exportCSV();

// ---------------------------------------------------------
// PAGINATION CÔTÉ SERVEUR
// ---------------------------------------------------------
const onPageChange = (event) => {
    router.get(
        route("employees.index"),
        {
            page: event.page + 1,
            search: search.value,
            per_page: event.rows,
        },
        { preserveState: true },
    );
};

// ---------------------------------------------------------
// TRI CÔTÉ SERVEUR
// ---------------------------------------------------------
const onSort = (event) => {
    router.get(
        route("employees.index"),
        {
            search: search.value,
            page: 1,
            sort_field: event.sortField,
            sort_order: event.sortOrder === 1 ? "asc" : "desc",
        },
        { preserveState: true, replace: true },
    );
};

// ---------------------------------------------------------
// SUPPRESSION
// ---------------------------------------------------------
const confirmDelete = (employee) => {
    confirm.require({
        message: `Voulez-vous désactiver ${employee.first_name} ${employee.last_name} ?`,
        header: "Confirmation",
        icon: "pi pi-exclamation-triangle",
        rejectProps: { label: "Annuler", severity: "secondary", variant: "text" },
        acceptProps: { label: "Désactiver", severity: "danger" },
        accept: () => {
            router.delete(route("employees.destroy", employee.id), {
                preserveScroll: true,
                onSuccess: () => toast.add({ severity: "success", summary: "Archivé", detail: "Employé archivé.", life: 3000 }),
            });
        },
    });
};
</script>

<template>
    <AppLayout>
        <Toast />
        <ConfirmDialog />

        <div class="card">
            <!-- TOOLBAR -->
            <Toolbar class="mb-6">
                <template #start>
                    <Button
                        label="Nouvel employé"
                        icon="pi pi-plus"
                        class="mr-2 !bg-gradient-to-r !from-blue-600 !to-indigo-600 !border-0"
                        @click="openCreate"
                    />
                </template>
                <template #end>
                    <Button
                        label="Exporter"
                        icon="pi pi-upload"
                        severity="secondary"
                        @click="exportCSV"
                    />
                </template>
            </Toolbar>

            <!-- DATATABLE -->
            <DataTable
                ref="dt"
                :value="employees.data"
                :lazy="true"
                :paginator="true"
                :rows="employees.per_page"
                :totalRecords="employees.total"
                :rowsPerPageOptions="[10, 25, 50]"
                paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                currentPageReportTemplate="Affichage de {first} à {last} sur {totalRecords} employés"
                @page="onPageChange"
                @sort="onSort"
            >
                <template #header>
                    <div
                        class="flex flex-wrap gap-2 items-center justify-between"
                    >
                        <h4 class="m-0">Liste des employés</h4>
                        <IconField>
                            <InputIcon>
                                <i class="pi pi-search" />
                            </InputIcon>
                            <InputText
                                v-model="search"
                                placeholder="Rechercher..."
                                class="focus:!border-blue-700"
                            />
                        </IconField>
                    </div>
                </template>

                <Column
                    field="matricule"
                    header="Matricule"
                    sortable
                    style="min-width: 10rem"
                />
                <Column header="Nom complet" sortable style="min-width: 14rem">
                    <template #body="{ data }">
                        {{ data.first_name }} {{ data.last_name }}
                    </template>
                </Column>
                <Column header="Poste" style="min-width: 10rem">
                    <template #body="{ data }">
                        {{ data.position?.name ?? "—" }}
                    </template>
                </Column>
                <Column
                    field="email"
                    header="Email"
                    sortable
                    style="min-width: 14rem"
                />
                <Column
                    field="phone"
                    header="Téléphone"
                    style="min-width: 12rem"
                />
                <Column
                    field="status"
                    header="Statut"
                    sortable
                    style="min-width: 8rem"
                >
                    <template #body="{ data }">
                        <Tag
                            :value="data.status"
                            :severity="getStatusSeverity(data.status)"
                        />
                    </template>
                </Column>
                <Column
                    header="Actions"
                    :exportable="false"
                    style="min-width: 8rem"
                >
                    <template #body="{ data }">
                        <Button
                            icon="pi pi-eye"
                            variant="outlined"
                            rounded
                            class="mr-2"
                            @click="router.visit(route('employees.show', data.id))"
                        />
                        <Button
                            icon="pi pi-pencil"
                            variant="outlined"
                            rounded
                            class="mr-2"
                            @click="openEdit(data)"
                        />
                        <Button
                            v-if="data.status !== 'inactif'"
                            icon="pi pi-ban"
                            variant="outlined"
                            rounded
                            severity="danger"
                            @click="confirmDelete(data)"
                        />
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- ================================================ -->
        <!-- DIALOG — Formulaire Créer / Modifier             -->
        <!-- ================================================ -->
        <Dialog
            v-model:visible="dialogVisible"
            :header="isEditing ? 'Modifier l\'employé' : 'Nouvel employé'"
            :style="{ width: '650px' }"
            :modal="true"
            :closable="true"
            @hide="closeDialog"
        >
            <div class="flex flex-col gap-4 pt-2">
                <!-- Erreur globale -->
                <Message v-if="form.hasErrors" severity="error">
                    Veuillez corriger les erreurs ci-dessous.
                </Message>

                <!-- Matricule — lecture seule en mode édition -->
                <div v-if="isEditing" class="flex flex-col gap-1">
                    <label class="font-semibold text-slate-500"
                        >Matricule</label
                    >
                    <InputText
                        :value="form.matricule"
                        disabled
                        class="bg-slate-100 cursor-not-allowed focus:!border-blue-700"
                        fluid
                    />
                    <small class="text-slate-400"
                        >Le matricule est généré automatiquement et ne peut pas
                        être modifié.</small
                    >
                </div>

                <!-- Prénom / Nom -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold"
                            >Prénom <span class="text-red-500">*</span></label
                        >
                        <InputText
                            v-model="form.first_name"
                            :invalid="!!form.errors.first_name"
                            placeholder="Prénom"
                            fluid
                        />
                        <small
                            v-if="form.errors.first_name"
                            class="text-red-500"
                            >{{ form.errors.first_name }}</small
                        >
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold"
                            >Nom <span class="text-red-500">*</span></label
                        >
                        <InputText
                            v-model="form.last_name"
                            :invalid="!!form.errors.last_name"
                            placeholder="Nom"
                            fluid
                        />
                        <small
                            v-if="form.errors.last_name"
                            class="text-red-500"
                            >{{ form.errors.last_name }}</small
                        >
                    </div>
                </div>

                <!-- Email / Téléphone -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold"
                            >Email <span class="text-red-500">*</span></label
                        >
                        <InputText
                            v-model="form.email"
                            :invalid="!!form.errors.email"
                            placeholder="email@exemple.com"
                            fluid
                        />
                        <small v-if="form.errors.email" class="text-red-500">{{
                            form.errors.email
                        }}</small>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold">Téléphone</label>
                        <InputText
                            v-model="form.phone"
                            placeholder="+229 00 00 00 00"
                            fluid
                        />
                    </div>
                </div>

                <!-- Date de naissance -->
                <div class="flex flex-col gap-1">
                    <label class="font-semibold"
                        >Date de naissance
                        <span class="text-red-500">*</span></label
                    >
                    <DatePicker
                        v-model="form.birth_date"
                        :invalid="!!form.errors.birth_date"
                        dateFormat="dd/mm/yy"
                        :maxDate="new Date()"
                        showIcon
                        fluid
                    />
                    <small v-if="form.errors.birth_date" class="text-red-500">{{
                        form.errors.birth_date
                    }}</small>
                </div>

                <!-- Adresse -->
                <div class="flex flex-col gap-1">
                    <label class="font-semibold">Adresse</label>
                    <InputText
                        v-model="form.address"
                        placeholder="Adresse complète"
                        fluid
                        class="focus:!border-blue-700"
                    />
                </div>

                <!-- Poste / Statut -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold"
                            >Poste <span class="text-red-500">*</span></label
                        >
                        <Select
                            v-model="form.position_id"
                            :options="positions"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Sélectionner un poste"
                            :invalid="!!form.errors.position_id"
                            fluid
                        />
                        <small
                            v-if="form.errors.position_id"
                            class="text-red-500"
                            >{{ form.errors.position_id }}</small
                        >
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-semibold"
                            >Statut <span class="text-red-500">*</span></label
                        >
                        <Select
                            v-model="form.status"
                            :options="statuses"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Sélectionner un statut"
                            :invalid="!!form.errors.status"
                            fluid
                        />
                        <small v-if="form.errors.status" class="text-red-500">{{
                            form.errors.status
                        }}</small>
                    </div>
                </div>

                <!-- Salaire de base -->
                <div class="flex flex-col gap-1">
                    <label class="font-semibold"
                        >Salaire de base
                        <span class="text-red-500">*</span></label
                    >
                    <InputNumber
                        v-model="form.salary_base"
                        :invalid="!!form.errors.salary_base"
                        :min="0"
                        fluid
                    />
                    <small
                        v-if="form.errors.salary_base"
                        class="text-red-500"
                        >{{ form.errors.salary_base }}</small
                    >
                </div>

                <!-- User ID — optionnel -->
                <div class="flex flex-col gap-1">
                    <label class="font-semibold text-slate-500">
                        ID Compte utilisateur
                        <span class="text-xs font-normal text-slate-400"
                            >(optionnel)</span
                        >
                    </label>
                    <InputNumber
                        v-model="form.user_id"
                        :min="1"
                        placeholder="Laisser vide si aucun compte lié"
                        fluid
                    />
                    <small class="text-slate-400"
                        >Lier cet employé à un compte utilisateur
                        existant.</small
                    >
                </div>
            </div>

            <!-- FOOTER -->
            <template #footer>
                <Button
                    label="Annuler"
                    icon="pi pi-times"
                    severity="secondary"
                    variant="text"
                    @click="closeDialog"
                />
                <Button
                    :label="isEditing ? 'Mettre à jour' : 'Créer'"
                    icon="pi pi-check"
                    class="!bg-gradient-to-r !from-blue-600 !to-indigo-600 !border-0"
                    :loading="form.processing"
                    @click="submitForm"
                />
            </template>
        </Dialog>
    </AppLayout>
</template>
