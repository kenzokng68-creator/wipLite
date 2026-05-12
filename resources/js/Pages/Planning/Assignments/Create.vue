<script setup>
import { ref } from "vue";
import { Head, useForm, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import Button from "primevue/button";
import Dropdown from "primevue/dropdown";
import Calendar from "primevue/calendar";
import Dialog from "primevue/dialog";
import {
    Clock,
    ArrowLeft,
    Save,
    CheckCircle2,
    Users,
} from "lucide-vue-next";

const props = defineProps({
    supervisors: Array,
    models: Array,
    selected_supervisor_id: [String, Number],
});

const showSuccessModal = ref(false);

const form = useForm({
    supervisor_id: props.selected_supervisor_id ? Number(props.selected_supervisor_id) : null,
    planning_model_id: null,
    start_date: null,
    end_date: null,
});

const startDate = ref(null);
const endDate = ref(null);

const formatDate = (date) => {
    if (!date) return null;
    const d = new Date(date);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const submit = () => {
    form.start_date = formatDate(startDate.value);
    form.end_date = formatDate(endDate.value);
    form.post(route("planning.assignments.store"), {
        onSuccess: () => {
            showSuccessModal.value = true;
        },
    });
};

const goToIndex = () => {
    showSuccessModal.value = false;
    setTimeout(() => {
        router.visit(route('planning.assignments.index'));
    }, 100);
};
</script>

<template>
    <Head title="Créer une affectation de planning" />

    <AppLayout>
        <div class="max-w-2xl mx-auto mb-8 flex items-center gap-6 bg-white/50 backdrop-blur-sm p-6 rounded-[2rem] border border-white shadow-sm">
            <Button
                @click="goToIndex"
                class="!bg-white !text-slate-600 !border-slate-200 !rounded-xl shadow-sm hover:!bg-slate-50 !w-12 !h-12 !p-0 flex items-center justify-center"
                aria-label="Retour"
            >
                <ArrowLeft class="w-5 h-5" />
            </Button>
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Nouvelle Affectation</h2>
                <p class="text-blue-500/70 text-xs font-bold uppercase tracking-widest mt-1">Assigner un planning à un superviseur et son équipe</p>
            </div>
        </div>

        <div class="max-w-2xl mx-auto py-8">
            <form @submit.prevent="submit" class="space-y-6">

                <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm space-y-6">
                    <div class="flex flex-col gap-3">
                        <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Superviseur</label>
                        <Dropdown
                            v-model="form.supervisor_id"
                            :options="supervisors"
                            option-label="name"
                            option-value="id"
                            placeholder="Sélectionnez un superviseur"
                            :class="{'p-invalid': form.errors.supervisor_id}"
                            class="!w-full !rounded-2xl !py-4 !bg-slate-50/50 !border-slate-100 focus:!bg-white focus:!border-blue-500"
                        />
                        <small class="text-red-500 font-bold" v-if="form.errors.supervisor_id">{{ form.errors.supervisor_id }}</small>
                    </div>

                    <div class="flex flex-col gap-3">
                        <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Modèle de planning</label>
                        <Dropdown
                            v-model="form.planning_model_id"
                            :options="models"
                            option-label="name"
                            option-value="id"
                            placeholder="Sélectionnez un modèle"
                            :class="{'p-invalid': form.errors.planning_model_id}"
                            class="!w-full !rounded-2xl !py-4 !bg-slate-50/50 !border-slate-100 focus:!bg-white focus:!border-blue-500"
                        >
                            <template #option="slotProps">
                                <div class="flex justify-between items-center">
                                    <span>{{ slotProps.option.name }}</span>
                                    <span class="text-xs text-slate-400">{{ slotProps.option.total_hours }}h</span>
                                </div>
                            </template>
                        </Dropdown>
                        <small class="text-red-500 font-bold" v-if="form.errors.planning_model_id">{{ form.errors.planning_model_id }}</small>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-3">
                            <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Date de début</label>
                            <Calendar
                                v-model="startDate"
                                date-format="yy-mm-dd"
                                :min-date="new Date()"
                                placeholder="Sélectionnez la date"
                                :class="{'p-invalid': form.errors.start_date}"
                                input-class="!w-full !rounded-2xl !py-4 !px-6 !bg-slate-50/50 !border-slate-100 focus:!bg-white focus:!border-blue-500"
                            />
                            <small class="text-red-500 font-bold" v-if="form.errors.start_date">{{ form.errors.start_date }}</small>
                        </div>
                        <div class="flex flex-col gap-3">
                            <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Date de fin</label>
                            <Calendar
                                v-model="endDate"
                                date-format="yy-mm-dd"
                                :min-date="startDate || new Date()"
                                placeholder="Sélectionnez la date"
                                :class="{'p-invalid': form.errors.end_date}"
                                input-class="!w-full !rounded-2xl !py-4 !px-6 !bg-slate-50/50 !border-slate-100 focus:!bg-white focus:!border-blue-500"
                            />
                            <small class="text-red-500 font-bold" v-if="form.errors.end_date">{{ form.errors.end_date }}</small>
                        </div>
                    </div>
                </div>

                <Button
                    type="submit"
                    :loading="form.processing"
                    class="!w-full !bg-blue-600 !border-none !py-6 !rounded-[1.5rem] !text-lg !font-black !text-white shadow-xl shadow-blue-100 hover:!bg-blue-700 hover:-translate-y-1 transition-all"
                >
                    <Save class="w-5 h-5 mr-3" />
                    Enregistrer l'affectation
                </Button>
            </form>
        </div>

        <Dialog
            v-model="showSuccessModal"
            modal
            :closable="false"
            :style="{ width: '28rem' }"
            :pt="{
                root: { class: '!rounded-[2.5rem] !bg-white !p-0 !overflow-hidden' },
                content: { class: '!p-0' }
            }"
        >
            <div class="p-10 text-center">
                <div class="w-24 h-24 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <CheckCircle2 class="w-12 h-12 text-green-500" />
                </div>
                <h3 class="text-2xl font-black text-slate-800 mb-2">Affectations créées !</h3>
                <p class="text-slate-500 font-medium leading-relaxed mb-8">
                    Les affectations ont été créées avec succès et sont en attente de validation.
                </p>
                <div class="flex flex-col gap-3">
                    <Button
                        label="Retour à la liste"
                        @click="goToIndex"
                        class="!w-full !bg-slate-900 !border-none !py-4 !rounded-2xl !font-bold"
                    />
                </div>
            </div>
        </Dialog>

    </AppLayout>
</template>
