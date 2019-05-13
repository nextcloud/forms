<template>
	<div class="loading-overlay">
		<span class="icon-loading" />
	</div>
</template>

<style lang="scss">
.loading-overlay {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: #fff;
    opacity: 0.9;
    z-index: 1001;
    .icon-loading {
        position: fixed;
        left: 50%;
        top: 50%;
        margin-left: -35px;
        margin-top: -10px;
        &::after {
            border: 10px solid var(--color-loading-light);
            border-top-color: var(--color-primary-element);
            height: 70px;
            width: 70px;
        }
    }
}
</style>
