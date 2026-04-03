<script setup>
import PublicAuthLayout from "@/Layouts/PublicAuthLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import Button from "primevue/button";
import Checkbox from "primevue/checkbox";
import InputText from "primevue/inputtext";
import Message from "primevue/message";
import Password from "primevue/password";

defineProps({
    canResetPassword: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const submit = () => {
    form.post(route("login"), {
        onFinish: () => form.reset("password"),
    });
};
</script>

<template>
    <Head title="Log in" />

    <PublicAuthLayout title="Sign in" description="Sign in to your account to continue.">
        <Message v-if="status" severity="success" class="mb-5">{{ status }}</Message>

        <form class="space-y-5" @submit.prevent="submit">
            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-700" for="email"
                    >Email</label
                >
                <InputText
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="w-full"
                    autofocus
                    autocomplete="username"
                />
                <small v-if="form.errors.email" class="text-red-600">{{
                    form.errors.email
                }}</small>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-700" for="password"
                    >Password</label
                >
                <Password
                    id="password"
                    v-model="form.password"
                    input-class="w-full"
                    class="w-full"
                    :feedback="false"
                    toggle-mask
                    autocomplete="current-password"
                />
                <small v-if="form.errors.password" class="text-red-600">{{
                    form.errors.password
                }}</small>
            </div>

            <div class="flex items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <Checkbox v-model="form.remember" binary input-id="remember" />
                    Remember me
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-sky-700"
                >
                    Forgot password?
                </Link>
            </div>

            <Button
                type="submit"
                label="Sign in"
                icon="pi pi-sign-in"
                class="w-full justify-center"
                :loading="form.processing"
            />
        </form>
    </PublicAuthLayout>
</template>
